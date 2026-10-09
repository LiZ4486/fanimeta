<?php
/**
 * 自建账号系统：登录 / 注册 / 找回密码 / 重置密码 / 退出
 *
 * 设计要点
 * -------
 * 1. 前台完全不再使用 wp-login.php：所有账号相关入口都走本文件注册的路由。
 *    wp-login.php 仍然保留（wp-admin 的认证依赖它），但前台不再有任何链接指向它，
 *    且其中的「注册」「找回密码」会被重定向到自建页面，避免两套流程并存。
 * 2. 用户体系仍然复用 WordPress 的 wp_users / wp_usermeta —— 只是换了交互层。
 *    登录用 wp_signon()，注册用 register_new_user()，重置用 reset_password()，
 *    因此原有的「昵称规则 / 邀请码 / 邮箱验证码」校验钩子全部自动生效。
 * 3. 表单提交采用 PRG（Post-Redirect-Get）模式，避免刷新重复提交；
 *    提示信息通过一次性 transient 传递，不放 URL 里，避免信息泄露与 XSS。
 *
 * @package Fanimeta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 路由 query var 名 */
define( 'FANIMETA_AUTH_VAR', 'fanimeta_auth' );

/** 登录失败锁定：单 IP 最多尝试次数 */
define( 'FANIMETA_AUTH_MAX_TRY', 8 );

/** 登录失败锁定：单账号最多尝试次数（防止攻击者换 IP 绕过 IP 维度限制） */
define( 'FANIMETA_AUTH_ACCOUNT_MAX_TRY', 5 );

/** 登录失败锁定：锁定时长（秒） */
define( 'FANIMETA_AUTH_LOCK_TTL', 900 );

/** 找回密码发信冷却（秒，按账号算） */
define( 'FANIMETA_AUTH_RESET_COOLDOWN', 60 );

/** 找回密码发信：单 IP 每小时最多请求次数（防止换邮箱轰炸） */
define( 'FANIMETA_AUTH_RESET_IP_MAX', 5 );

/** 匿名访客 CSRF token 的 Cookie 名 */
define( 'FANIMETA_AUTH_CSRF_COOKIE', 'fanimeta_csrf' );

/* ==========================================================================
 * 一、路由与地址
 * ========================================================================== */

/**
 * 路由 => URL 路径 映射。
 *
 * @return array
 */
function fanimeta_auth_route_map() {
	return array(
		'login'         => 'login',
		'register'      => 'register',
		'lostpassword'  => 'lost-password',
		'resetpassword' => 'reset-password',
		'logout'        => 'logout',
	);
}

/**
 * 生成某个路由的地址。
 *
 * 固定链接为「朴素」模式时，rewrite 规则不生效，自动回退成 ?fanimeta_auth=xxx。
 *
 * @param string $route login|register|lostpassword|resetpassword|logout。
 * @param array  $args  附加查询参数。
 * @return string
 */
function fanimeta_auth_url( $route = 'login', $args = array() ) {
	$map  = fanimeta_auth_route_map();
	$slug = isset( $map[ $route ] ) ? $map[ $route ] : $map['login'];

	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		$url = add_query_arg( FANIMETA_AUTH_VAR, $route, home_url( '/' ) );
	} else {
		$url = home_url( '/' . $slug . '/' );
	}

	if ( ! empty( $args ) ) {
		$url = add_query_arg( $args, $url );
	}

	return $url;
}

/**
 * 登录页地址。
 *
 * @param string $redirect 登录成功后的回跳地址。
 * @return string
 */
function fanimeta_login_url( $redirect = '' ) {
	$args = array();
	if ( '' !== $redirect ) {
		$args['redirect_to'] = $redirect;
	}
	return fanimeta_auth_url( 'login', $args );
}

/**
 * 注册页地址。
 *
 * @return string
 */
function fanimeta_register_url() {
	return fanimeta_auth_url( 'register' );
}

/**
 * 找回密码页地址。
 *
 * @return string
 */
function fanimeta_lostpassword_url() {
	return fanimeta_auth_url( 'lostpassword' );
}

/**
 * 退出地址（带 nonce，防止被 CSRF 强制登出）。
 *
 * @param string $redirect 退出后的回跳地址。
 * @return string
 */
function fanimeta_logout_url( $redirect = '' ) {
	$url = wp_nonce_url( fanimeta_auth_url( 'logout' ), 'fanimeta_logout' );
	if ( '' !== $redirect ) {
		$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
	}
	return $url;
}

/**
 * 从 REQUEST_URI 兜底识别路由。
 *
 * 作用：rewrite 规则尚未刷新（例如刚导入数据库、规则缓存是旧的）时，
 * 地址仍然能被正确识别，不会出现 404。
 *
 * @return string
 */
function fanimeta_auth_route_from_uri() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return '';
	}

	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$path = trim( (string) $path, '/' );

	$lookup = array_flip( fanimeta_auth_route_map() );
	if ( isset( $lookup[ $path ] ) ) {
		return $lookup[ $path ];
	}

	return '';
}

/**
 * 当前请求命中的账号系统路由，未命中返回空字符串。
 *
 * @return string
 */
function fanimeta_auth_current_route() {
	$route = get_query_var( FANIMETA_AUTH_VAR );

	if ( empty( $route ) && isset( $_GET[ FANIMETA_AUTH_VAR ] ) ) {
		$route = sanitize_key( wp_unslash( $_GET[ FANIMETA_AUTH_VAR ] ) );
	}

	if ( empty( $route ) ) {
		$route = fanimeta_auth_route_from_uri();
	}

	$route = sanitize_key( (string) $route );

	return array_key_exists( $route, fanimeta_auth_route_map() ) ? $route : '';
}

/**
 * 注册 rewrite 规则。
 */
function fanimeta_auth_rewrite() {
	foreach ( fanimeta_auth_route_map() as $route => $slug ) {
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?' . FANIMETA_AUTH_VAR . '=' . $route, 'top' );
	}
}
add_action( 'init', 'fanimeta_auth_rewrite' );

/**
 * 注册 query var。
 *
 * @param array $vars 现有 query vars。
 * @return array
 */
function fanimeta_auth_query_vars( $vars ) {
	$vars[] = FANIMETA_AUTH_VAR;
	return $vars;
}
add_filter( 'query_vars', 'fanimeta_auth_query_vars' );

/**
 * 必要时刷新 rewrite 规则（主题版本变化、或规则缺失时）。
 */
function fanimeta_auth_maybe_flush() {
	$rules = get_option( 'rewrite_rules' );
	$stale = ( get_option( 'fanimeta_auth_rewrite_ver' ) !== FANIMETA_VERSION )
		|| ! is_array( $rules )
		|| ! isset( $rules['^login/?$'] );

	if ( $stale ) {
		flush_rewrite_rules( false );
		update_option( 'fanimeta_auth_rewrite_ver', FANIMETA_VERSION );
	}
}
add_action( 'init', 'fanimeta_auth_maybe_flush', 99 );
add_action( 'after_switch_theme', 'fanimeta_auth_maybe_flush' );

/* ==========================================================================
 * 二、请求接管
 * ========================================================================== */

/**
 * 账号系统请求的引导：拦截规范跳转、修正查询状态、处理 POST。
 */
function fanimeta_auth_boot() {
	$route = fanimeta_auth_current_route();

	// 先处理表单提交（不依赖 $route —— 表单可能从任意地址提交过来）。
	fanimeta_auth_handle_post();

	if ( '' === $route ) {
		return;
	}

	/*
	 * wp_redirect_admin_locations() 会把 /login 之类的地址跳去 wp-login.php，
	 * redirect_canonical() 又会把没有匹配文章的地址跳去首页，两者都会打断我们的路由。
	 */
	remove_action( 'template_redirect', 'redirect_canonical' );
	remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

	global $wp_query;
	if ( $wp_query ) {
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
	}

	status_header( 200 );
	nocache_headers();

	if ( 'logout' === $route ) {
		fanimeta_auth_do_logout();
	}

	// 已登录用户访问登录 / 注册 / 找回密码页时直接回首页，避免重复认证。
	if ( is_user_logged_in() && in_array( $route, array( 'login', 'register', 'lostpassword' ), true ) ) {
		fanimeta_auth_redirect( home_url( '/' ) );
	}
}
add_action( 'template_redirect', 'fanimeta_auth_boot', 0 );

/**
 * 把账号系统的路由指向对应模板。
 *
 * @param string $template 默认模板路径。
 * @return string
 */
function fanimeta_auth_template_include( $template ) {
	$route = fanimeta_auth_current_route();
	if ( '' === $route ) {
		return $template;
	}

	$map = array(
		'login'         => 'auth-login.php',
		'register'      => 'auth-register.php',
		'lostpassword'  => 'auth-lostpassword.php',
		'resetpassword' => 'auth-resetpassword.php',
	);

	if ( ! isset( $map[ $route ] ) ) {
		return $template;
	}

	$file = get_template_directory() . '/templates/' . $map[ $route ];
	if ( file_exists( $file ) ) {
		return $file;
	}

	return $template;
}
add_filter( 'template_include', 'fanimeta_auth_template_include', 99 );

/**
 * 账号页的浏览器标签标题，例如「登录 · 站点名」。
 *
 * @param string $title 默认标题。
 * @return string
 */
function fanimeta_auth_document_title( $title ) {
	$names = array(
		'login'         => '登录',
		'register'      => '注册',
		'lostpassword'  => '找回密码',
		'resetpassword' => '设置新密码',
	);

	$route = fanimeta_auth_current_route();
	if ( '' === $route || ! isset( $names[ $route ] ) ) {
		return $title;
	}

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	return $names[ $route ] . ' · ' . $site;
}
add_filter( 'pre_get_document_title', 'fanimeta_auth_document_title', 99 );

/* ==========================================================================
 * 三、工具函数
 * ========================================================================== */

/**
 * 读取并消费一次性提示（transient 存，用完即删）。
 *
 * @return array
 */
function fanimeta_auth_take_flash() {
	if ( empty( $_GET['fl'] ) ) {
		return array();
	}

	$token = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) wp_unslash( $_GET['fl'] ) ) );
	if ( '' === $token ) {
		return array();
	}

	$key  = 'fanimeta_auth_fl_' . $token;
	$data = get_transient( $key );
	if ( false === $data ) {
		return array();
	}

	delete_transient( $key );

	return is_array( $data ) ? $data : array();
}

/**
 * 生成带一次性提示的重定向地址。
 *
 * @param string $url  目标地址。
 * @param array  $data 提示数据（error / success / values）。
 * @return string
 */
function fanimeta_auth_flash_url( $url, $data ) {
	$token = strtolower( wp_generate_password( 20, false ) );
	set_transient( 'fanimeta_auth_fl_' . $token, $data, 5 * MINUTE_IN_SECONDS );
	return add_query_arg( 'fl', $token, $url );
}

/**
 * 渲染提示条。
 *
 * @param array $flash fanimeta_auth_take_flash() 的返回值。
 */
function fanimeta_auth_notice( $flash ) {
	if ( ! empty( $flash['error'] ) ) {
		echo '<div class="auth-notice auth-notice-error" role="alert">' . esc_html( $flash['error'] ) . '</div>';
	} elseif ( ! empty( $flash['success'] ) ) {
		echo '<div class="auth-notice auth-notice-success" role="alert">' . esc_html( $flash['success'] ) . '</div>';
	}
}

/**
 * 回填已提交的字段值。
 *
 * @param array  $flash 提示数据。
 * @param string $key   字段名。
 * @return string
 */
function fanimeta_auth_old( $flash, $key ) {
	return isset( $flash['values'][ $key ] ) ? (string) $flash['values'][ $key ] : '';
}

/**
 * 校验并归一化回跳地址，只允许站内。
 *
 * @param string $url 待校验地址。
 * @return string
 */
function fanimeta_auth_safe_redirect( $url ) {
	$url = trim( (string) wp_unslash( $url ) );
	if ( '' === $url ) {
		return '';
	}

	// 协议相对或站外地址一律丢弃。
	if ( 0 === strpos( $url, '//' ) ) {
		return '';
	}

	// 站内相对路径。
	if ( 0 === strpos( $url, '/' ) ) {
		return home_url( $url );
	}

	$home = home_url( '/' );
	if ( 0 === strpos( $url, $home ) ) {
		return $url;
	}

	$site = site_url( '/' );
	if ( 0 === strpos( $url, $site ) ) {
		return $url;
	}

	return '';
}

/**
 * 带兜底地址的安全跳转并结束请求。
 *
 * @param string $url 目标地址。
 */
function fanimeta_auth_redirect( $url ) {
	wp_safe_redirect( $url ? $url : home_url( '/' ), 302, home_url( '/' ) );
	exit;
}

/* --------------------------------------------------------------------------
 * CSRF 防护
 *
 * WordPress 的 wp_create_nonce() 把 user_id 与 session_token 掺进哈希，
 * 未登录时两者分别是 0 与空串 —— 于是「所有匿名访客算出同一个 nonce」，
 * 在 12 小时时间窗内还是固定的。这意味着匿名表单的 nonce 形同虚设。
 *
 * 这里用两颗子弹补上：
 *   1) 给每个访客下发一份随机盐（Cookie），通过 nonce_user_logged_out
 *      过滤器掺进哈希 —— 每个访客从此拥有自己的 nonce；
 *   2) 表单提交时再做一次同源校验（Origin / Referer），挡掉第三方页面直接提交。
 * -------------------------------------------------------------------------- */

/**
 * 访客专属随机盐（读不到就生成一个并写 Cookie）。
 *
 * @return string 32 位十六进制字符串。
 */
function fanimeta_auth_anon_salt() {
	if ( ! empty( $_COOKIE[ FANIMETA_AUTH_CSRF_COOKIE ] ) ) {
		$salt = (string) wp_unslash( $_COOKIE[ FANIMETA_AUTH_CSRF_COOKIE ] );
		if ( preg_match( '/^[a-f0-9]{32}$/', $salt ) ) {
			return $salt;
		}
	}

	try {
		$salt = bin2hex( random_bytes( 16 ) );
	} catch ( Exception $e ) {
		$salt = md5( uniqid( (string) wp_rand(), true ) );
	}

	// init 阶段会先调用一次本函数（见下），确保此时 headers 尚未发出；
	// 之后模板里再调用时直接复用已生成的值，不会触发 "headers already sent"。
	if ( ! headers_sent() ) {
		setcookie(
			FANIMETA_AUTH_CSRF_COOKIE,
			$salt,
			array(
				'expires'  => time() + DAY_IN_SECONDS,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	$_COOKIE[ FANIMETA_AUTH_CSRF_COOKIE ] = $salt;

	return $salt;
}

/**
 * 提前把盐的 Cookie 发下去（init 阶段 headers 还没输出，一定成功）。
 */
function fanimeta_auth_anon_salt_boot() {
	fanimeta_auth_anon_salt();
}
add_action( 'init', 'fanimeta_auth_anon_salt_boot', 0 );

/**
 * 把访客盐掺进匿名 nonce 的计算。
 *
 * 只对本主题自建的账号系统生效（action 以 fanimeta_ 开头），
 * 不影响 WordPress 核心与其它插件的 nonce。
 *
 * @param int|string $uid    未登录时传入的 user ID。
 * @param string     $action nonce 动作名。
 * @return int|string
 */
function fanimeta_auth_nonce_salt( $uid, $action = '' ) {
	if ( $uid ) {
		return $uid;
	}

	if ( 0 !== strpos( (string) $action, 'fanimeta_' ) ) {
		return $uid;
	}

	return 'anon-' . fanimeta_auth_anon_salt();
}
add_filter( 'nonce_user_logged_out', 'fanimeta_auth_nonce_salt', 10, 2 );

/**
 * 同源校验：判断本次提交是否由本站页面发起。
 *
 * @return bool
 */
function fanimeta_auth_is_same_origin() {
	$origin = '';

	if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
		$origin = (string) wp_unslash( $_SERVER['HTTP_ORIGIN'] );
	} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$origin = (string) wp_unslash( $_SERVER['HTTP_REFERER'] );
	}

	// 两个头都没有（部分隐私设置会剥离 Referer）：不因此阻断正常用户。
	if ( '' === $origin ) {
		return true;
	}

	// 来自 sandbox iframe / data: / file: 的提交，Origin 字面量就是 "null"。
	if ( 'null' === strtolower( trim( $origin ) ) ) {
		return false;
	}

	$host        = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
	$scheme      = strtolower( (string) wp_parse_url( $origin, PHP_URL_SCHEME ) );
	$home        = home_url( '/' );
	$home_host   = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
	$home_scheme = strtolower( (string) wp_parse_url( $home, PHP_URL_SCHEME ) );

	if ( '' === $host || '' === $home_host ) {
		return true;
	}

	if ( $host !== $home_host ) {
		return false;
	}

	if ( '' !== $scheme && '' !== $home_scheme && $scheme !== $home_scheme ) {
		return false;
	}

	return true;
}

/**
 * 客户端标识（用于限流）。
 *
 * @return string
 */
function fanimeta_auth_client_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	return 'fanimeta_auth_lk_' . md5( $ip );
}

/**
 * 当前客户端是否因失败次数过多而被锁定。
 *
 * @return string 锁定提示，未锁定返回空字符串。
 */
function fanimeta_auth_lock_message() {
	$fails = (int) get_transient( fanimeta_auth_client_key() );
	if ( $fails >= FANIMETA_AUTH_MAX_TRY ) {
		return '尝试次数过多，请 ' . (int) ceil( FANIMETA_AUTH_LOCK_TTL / 60 ) . ' 分钟后再试。';
	}
	return '';
}

/**
 * 记录一次登录失败。
 */
function fanimeta_auth_note_failure() {
	$key   = fanimeta_auth_client_key();
	$fails = (int) get_transient( $key );
	set_transient( $key, $fails + 1, FANIMETA_AUTH_LOCK_TTL );
}

/**
 * 登录成功后清除失败计数。
 */
function fanimeta_auth_clear_failure() {
	delete_transient( fanimeta_auth_client_key() );
}

/**
 * 账号维度限流键。
 *
 * 为什么需要账号维度：只按 IP 计数时，攻击者用代理池即可绕过；
 * 而同一 NAT（学校 / 公司）下的正常用户又会互相牵连锁定。
 * 两个维度叠加，才是既挡得住爆破、又不过度误伤的折中。
 *
 * @param string $user_login 登录名 / 昵称。
 * @return string
 */
function fanimeta_auth_account_key( $user_login ) {
	return 'fanimeta_auth_ak_' . md5( strtolower( trim( (string) $user_login ) ) );
}

/**
 * 该账号是否因失败次数过多被临时锁定。
 *
 * @param string $user_login 登录名。
 * @return string 锁定提示，未锁定返回空字符串。
 */
function fanimeta_auth_account_lock_message( $user_login ) {
	$user_login = trim( (string) $user_login );
	if ( '' === $user_login ) {
		return '';
	}

	$fails = (int) get_transient( fanimeta_auth_account_key( $user_login ) );
	if ( $fails >= FANIMETA_AUTH_ACCOUNT_MAX_TRY ) {
		return '该账号尝试次数过多，请 ' . (int) ceil( FANIMETA_AUTH_LOCK_TTL / 60 ) . ' 分钟后再试。';
	}

	return '';
}

/**
 * 记录一次针对某账号的登录失败。
 *
 * @param string $user_login 登录名。
 */
function fanimeta_auth_note_account_failure( $user_login ) {
	$user_login = trim( (string) $user_login );
	if ( '' === $user_login ) {
		return;
	}

	$key   = fanimeta_auth_account_key( $user_login );
	$fails = (int) get_transient( $key );
	set_transient( $key, $fails + 1, FANIMETA_AUTH_LOCK_TTL );
}

/**
 * 清除某账号的失败计数。
 *
 * @param string $user_login 登录名。
 */
function fanimeta_auth_clear_account_failure( $user_login ) {
	$user_login = trim( (string) $user_login );
	if ( '' !== $user_login ) {
		delete_transient( fanimeta_auth_account_key( $user_login ) );
	}
}

/**
 * 按昵称 / 登录名 / 邮箱 / 显示名查找用户。
 *
 * @param string $input 用户输入。
 * @return WP_User|false
 */
function fanimeta_auth_find_user( $input ) {
	$input = trim( (string) $input );
	if ( '' === $input ) {
		return false;
	}

	if ( is_email( $input ) ) {
		$user = get_user_by( 'email', $input );
		if ( $user ) {
			return $user;
		}
	}

	$user = get_user_by( 'login', $input );
	if ( $user ) {
		return $user;
	}

	if ( is_numeric( $input ) ) {
		$user = get_user_by( 'id', (int) $input );
		if ( $user ) {
			return $user;
		}
	}

	$user = get_user_by( 'slug', $input );
	if ( $user ) {
		return $user;
	}

	// 兜底：按显示名匹配（WordPress 的 get_user_by 不支持 display_name）。
	$found = get_users(
		array(
			'search'         => $input,
			'search_columns' => array( 'display_name', 'nickname' ),
			'number'         => 1,
			'fields'         => 'all',
		)
	);

	return ! empty( $found ) ? $found[0] : false;
}

/**
 * 把 WP_Error 转成一条中文提示。
 *
 * @param WP_Error $error 错误对象。
 * @return string
 */
function fanimeta_auth_error_text( $error ) {
	if ( ! is_wp_error( $error ) ) {
		return '';
	}

	$map = array(
		'empty_username'       => '请填写账号昵称。',
		'invalid_username'     => '昵称只能包含中文、英文或数字。',
		/*
		 * 防枚举：不区分「昵称已被占用」与「邮箱已注册」。
		 * 否则注册页可被脚本用来批量筛选出"有效昵称 + 有效邮箱"清单，
		 * 直接喂给撞库与定向钓鱼。这与找回密码接口的防枚举策略保持一致。
		 */
		'username_exists'      => '该昵称或邮箱当前不可用，请更换后重试。',
		'email_exists'         => '该昵称或邮箱当前不可用，请更换后重试。',
		'existing_user_login'  => '该昵称或邮箱当前不可用，请更换后重试。',
		'existing_user_email'  => '该昵称或邮箱当前不可用，请更换后重试。',
		'fanimeta_nickname_exists' => '该昵称或邮箱当前不可用，请更换后重试。',
		'empty_email'          => '请填写邮箱地址。',
		'invalid_email'        => '邮箱格式不正确，请检查后重试。',
		'registerfail'         => '注册失败，请稍后重试或联系站长。',
	);

	foreach ( (array) $error->get_error_codes() as $code ) {
		if ( isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}

		// 主题自有钩子写入的中文提示：去掉 <strong>错误</strong>：前缀后直接用。
		$text = trim( wp_strip_all_tags( (string) $error->get_error_message( $code ) ) );
		$text = preg_replace( '/^错误\s*[：:]\s*/u', '', $text );
		if ( '' !== $text ) {
			return $text;
		}
	}

	return '操作未成功，请检查填写内容后重试。';
}

/* ==========================================================================
 * 四、表单处理
 * ========================================================================== */

/**
 * 统一处理账号系统表单提交。
 */
function fanimeta_auth_handle_post() {
	if ( empty( $_POST['fanimeta_auth_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['fanimeta_auth_action'] ) );

	switch ( $action ) {
		case 'login':
			fanimeta_auth_process_login();
			break;
		case 'register':
			fanimeta_auth_process_register();
			break;
		case 'lostpassword':
			fanimeta_auth_process_lostpassword();
			break;
		case 'resetpassword':
			fanimeta_auth_process_resetpassword();
			break;
	}
}

/**
 * 校验提交来源（同源 + nonce），失败则跳回并提示。
 *
 * @param string $nonce_action nonce 动作名。
 * @param string $fallback     失败时的回跳地址。
 */
function fanimeta_auth_require_nonce( $nonce_action, $fallback ) {
	if ( ! fanimeta_auth_is_same_origin() ) {
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $fallback, array( 'error' => '请求来源异常，请从本站页面重新提交。' ) ) );
	}

	$nonce = isset( $_POST['fanimeta_auth_nonce'] ) ? (string) wp_unslash( $_POST['fanimeta_auth_nonce'] ) : '';

	if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $fallback, array( 'error' => '页面已过期，请重新提交。' ) ) );
	}
}

/**
 * 处理登录。
 */
function fanimeta_auth_process_login() {
	$login_url = fanimeta_auth_url( 'login' );
	$redirect  = fanimeta_auth_safe_redirect( isset( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : '' );

	if ( '' !== $redirect ) {
		$login_url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login_url );
	}

	fanimeta_auth_require_nonce( 'fanimeta_auth_login', $login_url );

	$user_login = isset( $_POST['user_login'] ) ? trim( (string) wp_unslash( $_POST['user_login'] ) ) : '';
	$password   = isset( $_POST['user_password'] ) ? (string) wp_unslash( $_POST['user_password'] ) : '';
	$remember   = ! empty( $_POST['rememberme'] );

	$keep = array( 'values' => array( 'user_login' => $user_login ) );

	if ( '' === $user_login || '' === $password ) {
		$keep['error'] = '请填写账号和密码。';
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $login_url, $keep ) );
	}

	$lock = fanimeta_auth_lock_message();
	if ( '' !== $lock ) {
		$keep['error'] = $lock;
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $login_url, $keep ) );
	}

	// 账号维度锁定：与 IP 维度叠加，代理池也绕不过去。
	$account_lock = fanimeta_auth_account_lock_message( $user_login );
	if ( '' !== $account_lock ) {
		$keep['error'] = $account_lock;
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $login_url, $keep ) );
	}

	$user = wp_signon(
		array(
			'user_login'    => $user_login,
			'user_password' => $password,
			'remember'      => $remember,
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		fanimeta_auth_note_failure();
		fanimeta_auth_note_account_failure( $user_login );
		$keep['error'] = '账号或密码不正确，请重新输入。';
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $login_url, $keep ) );
	}

	fanimeta_auth_clear_failure();
	fanimeta_auth_clear_account_failure( $user_login );
	fanimeta_auth_redirect( '' !== $redirect ? $redirect : home_url( '/' ) );
}

/**
 * 处理注册。
 */
function fanimeta_auth_process_register() {
	$register_url = fanimeta_auth_url( 'register' );

	if ( ! get_option( 'users_can_register' ) ) {
		fanimeta_auth_redirect( fanimeta_auth_flash_url( fanimeta_auth_url( 'login' ), array( 'error' => '本站当前未开放注册。' ) ) );
	}

	fanimeta_auth_require_nonce( 'fanimeta_auth_register', $register_url );

	$user_login = isset( $_POST['user_login'] ) ? trim( (string) wp_unslash( $_POST['user_login'] ) ) : '';
	$user_email = isset( $_POST['user_email'] ) ? trim( (string) wp_unslash( $_POST['user_email'] ) ) : '';

	$keep = array(
		'values' => array(
			'user_login'         => $user_login,
			'user_email'         => $user_email,
			'fanimeta_invite_code' => isset( $_POST['fanimeta_invite_code'] ) ? trim( (string) wp_unslash( $_POST['fanimeta_invite_code'] ) ) : '',
		),
	);

	/*
	 * 复用 WordPress 原生注册流程：
	 * register_new_user() 会触发 registration_errors 钩子，
	 * 主题里已有的「昵称规则 / 密码规则 / 邮箱验证码 / 邀请码」校验全部自动生效；
	 * 创建用户后 user_register 钩子会补齐密码、消耗邀请码、作废验证码并自动登录。
	 */
	$result = register_new_user( $user_login, $user_email );

	if ( is_wp_error( $result ) ) {
		$keep['error'] = fanimeta_auth_error_text( $result );
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $register_url, $keep ) );
	}

	fanimeta_auth_clear_failure();
	fanimeta_auth_redirect( home_url( '/' ) );
}

/**
 * 处理找回密码（发送重置邮件）。
 */
function fanimeta_auth_process_lostpassword() {
	$lost_url = fanimeta_auth_url( 'lostpassword' );

	fanimeta_auth_require_nonce( 'fanimeta_auth_lostpassword', $lost_url );

	$input = isset( $_POST['user_login'] ) ? trim( (string) wp_unslash( $_POST['user_login'] ) ) : '';
	$keep  = array( 'values' => array( 'user_login' => $input ) );

	if ( '' === $input ) {
		$keep['error'] = '请填写账号昵称或邮箱。';
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $lost_url, $keep ) );
	}

	$cool_key = 'fanimeta_auth_rst_cool_' . md5( strtolower( $input ) );
	if ( get_transient( $cool_key ) ) {
		$keep['error'] = '发送太频繁了，请稍等一会儿再试。';
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $lost_url, $keep ) );
	}

	/*
	 * 邮件轰炸防护。
	 * 上面那层冷却只按「输入的账号」算，攻击者换个邮箱就绕过去了，
	 * 而 WordPress 核心的 retrieve_password() 更是完全没有冷却。
	 * 因此这里再补两层：单 IP 每小时上限 + 全站每日发信上限
	 * （与注册验证码共用同一份日额度，避免两条通道各自把 DirectMail 配额打空）。
	 */
	$ip_reset_key = 'fanimeta_auth_rst_ip_' . md5( fanimeta_client_ip() );
	$ip_reset_cnt = (int) get_transient( $ip_reset_key );
	if ( $ip_reset_cnt >= FANIMETA_AUTH_RESET_IP_MAX ) {
		$keep['error'] = '操作过于频繁，请稍后再试。';
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $lost_url, $keep ) );
	}

	if ( fanimeta_mail_quota_day_full() ) {
		$keep['error'] = '今日发信量已达上限，请明日再试。';
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $lost_url, $keep ) );
	}

	set_transient( $cool_key, 1, FANIMETA_AUTH_RESET_COOLDOWN );

	$user = fanimeta_auth_find_user( $input );

	if ( $user && fanimeta_smtp_configured() ) {
		$key = get_password_reset_key( $user );

		if ( ! is_wp_error( $key ) ) {
			$link = fanimeta_auth_url(
				'resetpassword',
				array(
					'key'   => $key,
					'login' => rawurlencode( $user->user_login ),
				)
			);

			// 确认真的要发信了，此时才占用 IP 额度与每日额度。
			set_transient( $ip_reset_key, $ip_reset_cnt + 1, HOUR_IN_SECONDS );

			$sent = wp_mail(
				$user->user_email,
				sprintf( '【%s】重置登录密码', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
				fanimeta_auth_reset_email_body( $user, $link ),
				array( 'Content-Type: text/html; charset=UTF-8' )
			);

			if ( ! $sent ) {
				// 发信失败：退回冷却与额度，允许用户立即重试。
				delete_transient( $cool_key );
				delete_transient( $ip_reset_key );
			} else {
				fanimeta_mail_quota_day_add();
			}
		}
	}

	// 无论账号是否存在都返回相同提示，避免被用来枚举用户。
	fanimeta_auth_redirect(
		fanimeta_auth_flash_url(
			fanimeta_auth_url( 'login' ),
			array( 'success' => '如果该账号存在，重置密码的链接已发送到对应邮箱，请查收（24 小时内有效）。' )
		)
	);
}

/**
 * 处理重置密码。
 */
function fanimeta_auth_process_resetpassword() {
	$reset_url = fanimeta_auth_url( 'resetpassword' );

	$key   = isset( $_POST['rp_key'] ) ? (string) wp_unslash( $_POST['rp_key'] ) : '';
	$login = isset( $_POST['rp_login'] ) ? (string) wp_unslash( $_POST['rp_login'] ) : '';

	$back = add_query_arg(
		array(
			'key'   => rawurlencode( $key ),
			'login' => rawurlencode( $login ),
		),
		$reset_url
	);

	fanimeta_auth_require_nonce( 'fanimeta_auth_resetpassword', $back );

	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) ) {
		fanimeta_auth_redirect(
			fanimeta_auth_flash_url(
				fanimeta_auth_url( 'lostpassword' ),
				array( 'error' => '重置链接无效或已过期，请重新申请。' )
			)
		);
	}

	$pass1 = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
	$pass2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

	if ( '' === $pass1 ) {
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $back, array( 'error' => '请输入新密码。' ) ) );
	}

	if ( $pass1 !== $pass2 ) {
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $back, array( 'error' => '两次输入的密码不一致，请重新输入。' ) ) );
	}

	// 与注册页共用同一套强度校验，避免"注册严、重置松"被绕过。
	$fanimeta_check = fanimeta_password_check( $pass1, $user->user_login, $user->user_email );
	if ( is_wp_error( $fanimeta_check ) ) {
		fanimeta_auth_redirect( fanimeta_auth_flash_url( $back, array( 'error' => $fanimeta_check->get_error_message() ) ) );
	}

	reset_password( $user, $pass1 );

	fanimeta_auth_redirect(
		fanimeta_auth_flash_url(
			fanimeta_auth_url( 'login' ),
			array( 'success' => '密码已重置，请用新密码登录。' )
		)
	);
}

/**
 * 处理退出登录。
 */
function fanimeta_auth_do_logout() {
	$nonce = isset( $_GET['_wpnonce'] ) ? (string) wp_unslash( $_GET['_wpnonce'] ) : '';

	if ( ! wp_verify_nonce( $nonce, 'fanimeta_logout' ) ) {
		fanimeta_auth_redirect( home_url( '/' ) );
	}

	$redirect = fanimeta_auth_safe_redirect( isset( $_GET['redirect_to'] ) ? $_GET['redirect_to'] : '' );

	wp_logout();

	fanimeta_auth_redirect( '' !== $redirect ? $redirect : home_url( '/' ) );
}

/* ==========================================================================
 * 五、重置密码邮件
 * ========================================================================== */

/**
 * 重置密码邮件正文（HTML，与站点黄色卡片风格一致）。
 *
 * @param WP_User $user 目标用户。
 * @param string  $link 重置链接。
 * @return string
 */
function fanimeta_auth_reset_email_body( $user, $link ) {
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$site_url  = home_url( '/' );

	return '<div style="max-width:520px;margin:0 auto;padding:28px 24px;background:#faf6e8;border:2px solid #f7b500;border-radius:12px;font-family:-apple-system,\'Segoe UI\',\'Microsoft YaHei\',sans-serif;color:#5b5340;">'
		. '<h2 style="margin:0 0 12px;font-size:20px;color:#a67c00;">' . esc_html( $site_name ) . '</h2>'
		. '<p style="margin:0 0 18px;font-size:14px;line-height:1.7;">你好，' . esc_html( $user->display_name ) . '：<br>我们收到了重置「' . esc_html( $site_name ) . '」账号密码的请求，点击下面的按钮即可设置新密码。</p>'
		. '<p style="margin:0 0 18px;text-align:center;"><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:12px 30px;background:#f7b500;color:#3d3320;font-size:16px;font-weight:700;text-decoration:none;border-radius:10px;">重置密码</a></p>'
		. '<p style="margin:0 0 10px;font-size:13px;line-height:1.7;">链接 <strong>24 小时内</strong>有效，且只能使用一次。若不是你本人操作，忽略本邮件即可，你的密码不会发生变化。</p>'
		. '<p style="margin:0 0 4px;font-size:12px;line-height:1.6;word-break:break-all;color:#988f77;">按钮无法点击时，可复制以下地址到浏览器打开：<br>' . esc_html( $link ) . '</p>'
		. '<p style="margin:0;padding-top:14px;border-top:1px dashed #e0d9c3;font-size:12px;color:#988f77;">本邮件由系统自动发送，请勿直接回复。<br>' . esc_html( $site_url ) . '</p>'
		. '</div>';
}

/* ==========================================================================
 * 六、Ajax 登录（供弹窗使用）
 * ========================================================================== */

/**
 * Ajax：弹窗内提交登录。
 */
function fanimeta_ajax_login() {
	if ( ! fanimeta_auth_is_same_origin() ) {
		wp_send_json_error( array( 'message' => '请求来源异常，请刷新页面后重试。' ) );
	}

	$nonce = isset( $_POST['nonce'] ) ? (string) wp_unslash( $_POST['nonce'] ) : '';

	if ( ! wp_verify_nonce( $nonce, 'fanimeta_auth_login' ) ) {
		wp_send_json_error( array( 'message' => '页面已过期，请刷新后重试。' ) );
	}

	$user_login = isset( $_POST['user_login'] ) ? trim( (string) wp_unslash( $_POST['user_login'] ) ) : '';
	$password   = isset( $_POST['user_password'] ) ? (string) wp_unslash( $_POST['user_password'] ) : '';
	$remember   = ! empty( $_POST['rememberme'] );

	if ( '' === $user_login || '' === $password ) {
		wp_send_json_error( array( 'message' => '请填写账号和密码。' ) );
	}

	$lock = fanimeta_auth_lock_message();
	if ( '' !== $lock ) {
		wp_send_json_error( array( 'message' => $lock ) );
	}

	$account_lock = fanimeta_auth_account_lock_message( $user_login );
	if ( '' !== $account_lock ) {
		wp_send_json_error( array( 'message' => $account_lock ) );
	}

	$user = wp_signon(
		array(
			'user_login'    => $user_login,
			'user_password' => $password,
			'remember'      => $remember,
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		fanimeta_auth_note_failure();
		fanimeta_auth_note_account_failure( $user_login );
		wp_send_json_error( array( 'message' => '账号或密码不正确，请重新输入。' ) );
	}

	fanimeta_auth_clear_failure();
	fanimeta_auth_clear_account_failure( $user_login );

	$redirect = fanimeta_auth_safe_redirect( isset( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : '' );

	wp_send_json_success( array( 'redirect' => '' !== $redirect ? $redirect : home_url( '/' ) ) );
}
add_action( 'wp_ajax_nopriv_fanimeta_ajax_login', 'fanimeta_ajax_login' );
add_action( 'wp_ajax_fanimeta_ajax_login', 'fanimeta_ajax_login' );

/* ==========================================================================
 * 七、隐藏原生入口
 * ========================================================================== */

/**
 * 计算 wp-login.php 上需要被「送回自建页面」的动作目标地址。
 *
 * 关键点：wp-login.php 取动作用的是 $_REQUEST['action']（GET 与 POST 都算），
 * 所以这里也必须读 $_REQUEST。曾经常只读 $_GET —— 于是把 action 塞进 POST body
 * 就能绕过重定向，直接用到核心的「注册 / 找回密码」处理逻辑：
 * 核心 retrieve_password() 没有任何冷却机制，攻击者可以对任意邮箱高频触发，
 * 几小时内就能把邮件服务商的每日配额打空，让本站真实用户收不到验证码。
 *
 * @return string 需要跳转的地址；当前动作无需拦截时返回空字符串。
 */
function fanimeta_auth_wp_login_block_target() {
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

	switch ( $action ) {
		case 'register':
			return fanimeta_auth_url( 'register' );

		case 'lostpassword':
		case 'retrievepassword':
			return fanimeta_auth_url( 'lostpassword' );

		case 'rp':
		case 'resetpass':
			// 核心重置邮件里的链接会带 key / login，原样带过去，用户仍能完成重置。
			$target = fanimeta_auth_url( 'resetpassword' );
			$args   = array();

			if ( ! empty( $_REQUEST['key'] ) ) {
				$args['key'] = sanitize_text_field( wp_unslash( $_REQUEST['key'] ) );
			}

			if ( ! empty( $_REQUEST['login'] ) ) {
				$args['login'] = sanitize_text_field( wp_unslash( $_REQUEST['login'] ) );
			}

			return ! empty( $args ) ? add_query_arg( $args, $target ) : $target;
	}

	return '';
}

/**
 * 让 wp-login.php 只承担「后台入口」职责：
 * 其中的注册 / 找回密码 / 重置密码一律送回自建页面，两套流程不再并存。
 *
 * 有意同时挂两组钩子：
 * - login_form_{$action} 在 wp-login.php 的 switch 处理与表单渲染「之前」触发，
 *   这是拦住 POST 提交的关键（核心文件第 565 行 do_action，第 579 行才 switch）；
 * - login_init 触发更早，作为第一道闸，把 GET 拦在更前面。
 *
 * 注意：action=login（后台登录）与 action=logout、postpass 等必须放行，否则后台进不去。
 */
function fanimeta_auth_hide_wp_login() {
	$target = fanimeta_auth_wp_login_block_target();
	if ( '' === $target ) {
		return;
	}

	nocache_headers();
	wp_safe_redirect( $target, 302 );
	exit;
}
add_action( 'login_init', 'fanimeta_auth_hide_wp_login' );
add_action( 'login_form_register', 'fanimeta_auth_hide_wp_login' );
add_action( 'login_form_lostpassword', 'fanimeta_auth_hide_wp_login' );
add_action( 'login_form_retrievepassword', 'fanimeta_auth_hide_wp_login' );
add_action( 'login_form_rp', 'fanimeta_auth_hide_wp_login' );
add_action( 'login_form_resetpass', 'fanimeta_auth_hide_wp_login' );

/**
 * 让 wp_login_url()、wp_registration_url() 等原生助手也指向自建页面。
 *
 * 这样即使某处代码仍在调用原生助手，前台也不会把用户带去 wp-login.php。
 * 注意 login_url 过滤器签名是 ( $url, $redirect, $force_reauth )。
 *
 * @param string $url          默认地址。
 * @param string $redirect     登录成功后的回跳地址。
 * @param bool   $force_reauth 是否强制重新认证。
 * @return string
 */
function fanimeta_auth_login_url_filter( $url, $redirect = '', $force_reauth = false ) {
	return fanimeta_login_url( (string) $redirect );
}
add_filter( 'login_url', 'fanimeta_auth_login_url_filter', 10, 3 );

/**
 * 注册地址也指向自建注册页。
 *
 * @param string $url 默认地址。
 * @return string
 */
function fanimeta_auth_register_url_filter( $url ) {
	return fanimeta_auth_url( 'register' );
}
add_filter( 'register_url', 'fanimeta_auth_register_url_filter' );

/**
 * 找回密码地址也指向自建页面。
 *
 * @param string $url 默认地址。
 * @return string
 */
function fanimeta_auth_lostpassword_url_filter( $url ) {
	return fanimeta_auth_url( 'lostpassword' );
}
add_filter( 'lostpassword_url', 'fanimeta_auth_lostpassword_url_filter' );

/**
 * 退出地址也指向自建页面（原生 wp_logout_url 走的是 wp-login.php?action=logout）。
 *
 * @param string $url      默认地址。
 * @param string $redirect 退出后的回跳地址。
 * @return string
 */
function fanimeta_auth_logout_url_filter( $url, $redirect = '' ) {
	return fanimeta_logout_url( (string) $redirect );
}
add_filter( 'logout_url', 'fanimeta_auth_logout_url_filter', 10, 2 );

/* ==========================================================================
 * 八、资源加载
 * ========================================================================== */

/**
 * 加载账号系统样式与脚本（全站加载 —— 登录弹窗需要在任意页面可用）。
 */
function fanimeta_auth_assets() {
	wp_enqueue_style(
		'fanimeta-auth',
		get_template_directory_uri() . '/assets/css/auth.css',
		array( 'fanimeta-style' ),
		FANIMETA_VERSION
	);

	wp_enqueue_script(
		'fanimeta-auth',
		get_template_directory_uri() . '/assets/js/auth.js',
		array(),
		FANIMETA_VERSION,
		true
	);

	wp_localize_script(
		'fanimeta-auth',
		'fanimetaAuth',
		array(
			'ajax'       => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'fanimeta_auth_login' ),
			'emailNonce' => wp_create_nonce( 'fanimeta_email_code' ),
			'registerUrl' => fanimeta_auth_url( 'register' ),
			'lostUrl'    => fanimeta_auth_url( 'lostpassword' ),
			'labels'     => array(
				'submitting' => '登录中…',
				'resend'     => '重新获取',
				'sending'    => '正在发送…',
				'countdown'  => '%d 秒后可重发',
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fanimeta_auth_assets', 20 );
