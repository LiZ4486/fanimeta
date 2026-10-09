<?php
/**
 * Fanimeta 主题核心功能
 *
 * @package Fanimeta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FANIMETA_VERSION' ) ) {
	define( 'FANIMETA_VERSION', '1.13.1' );
}

/**
 * 安全与配额相关常量。
 *
 * 都可以在 wp-config.php 中先行定义同名常量覆盖（下方均有 defined() 保护）。
 */

/** 新用户密码最小长度 */
if ( ! defined( 'FANIMETA_PASSWORD_MIN_LEN' ) ) {
	define( 'FANIMETA_PASSWORD_MIN_LEN', 10 );
}

/** 全站每日发信上限（注册验证码与密码重置共享此额度，留出余量给邮件服务商配额） */
if ( ! defined( 'FANIMETA_MAIL_DAY_MAX' ) ) {
	define( 'FANIMETA_MAIL_DAY_MAX', 150 );
}

/** 单个 IP 每小时最多索取验证码的次数 */
if ( ! defined( 'FANIMETA_EMAIL_IP_MAX_PER_HOUR' ) ) {
	define( 'FANIMETA_EMAIL_IP_MAX_PER_HOUR', 10 );
}

/** 单个用户每小时最多投稿篇数 */
if ( ! defined( 'FANIMETA_SUBMIT_MAX_PER_HOUR' ) ) {
	define( 'FANIMETA_SUBMIT_MAX_PER_HOUR', 5 );
}

/** 单个用户每小时最多上传次数（头像 / 特色图） */
if ( ! defined( 'FANIMETA_UPLOAD_MAX_PER_HOUR' ) ) {
	define( 'FANIMETA_UPLOAD_MAX_PER_HOUR', 10 );
}

/**
 * 自建账号系统（登录 / 注册 / 找回密码 / 重置密码 / 退出）。
 * 前台不再使用 wp-login.php，详见 inc/auth.php 文件头说明。
 */
require_once get_template_directory() . '/inc/auth.php';

/**
 * 用户中心：投稿状态映射与「我的投稿」区块。
 * 必须先于 admin-panel.php 加载 —— 后者依赖本文件定义的 meta 常量。
 */
require_once get_template_directory() . '/inc/user-center.php';

/**
 * 后台运营面板：内容审核工作流 + 站点概览看板。
 */
require_once get_template_directory() . '/inc/admin-panel.php';

/**
 * 更新日志：站点左侧栏卡片 + /changelog/ 独立页面。
 * 数据写在 inc/changelog.php 顶部，改一行即可发一条。
 */
require_once get_template_directory() . '/inc/changelog.php';

// 站长信息：中文ID + B站个人主页链接（顶栏右侧展示）
if ( ! defined( 'FANIMETA_OWNER_NAME' ) ) {
	define( 'FANIMETA_OWNER_NAME', '栗小吱' );
}
if ( ! defined( 'FANIMETA_OWNER_BILIBILI_URL' ) ) {
	define( 'FANIMETA_OWNER_BILIBILI_URL', 'https://space.bilibili.com/289175907' );
}

/**
 * 主题设置
 */
function fanimeta_setup() {
	// 国际化
	load_theme_textdomain( 'fanimeta', get_template_directory() . '/languages' );

	// 让 WordPress 管理 <title>
	add_theme_support( 'title-tag' );

	// 特色图片
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 1200, 675, true );

	// 横版头图（16:9，居中硬裁剪）
	add_image_size( 'fanimeta-featured', 1200, 675, true );

	// 列表页小缩略图（16:9，占比小）
	add_image_size( 'fanimeta-list-thumb', 320, 180, true );

	// 自定义 Logo
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 96,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// 自动 feed 链接
	add_theme_support( 'automatic-feed-links' );

	// HTML5 语义
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);

	// 自定义背景
	add_theme_support(
		'custom-background',
		array(
			'default-color' => 'faf6e8',
		)
	);

	// 导航菜单
	register_nav_menus(
		array(
			'primary' => __( '主菜单', 'fanimeta' ),
			'social'  => __( '社交链接菜单', 'fanimeta' ),
		)
	);
}
add_action( 'after_setup_theme', 'fanimeta_setup' );

/**
 * 安全加固：隐藏 WordPress 版本号（防止通过 generator 标签探测版本）。
 */
add_filter( 'the_generator', '__return_empty_string' );

/**
 * 安全加固：未登录用户禁用 REST API 用户列表端点（防止用户名枚举）。
 *
 * @param array $endpoints REST 端点列表。
 * @return array 过滤后的端点列表。
 */
function fanimeta_harden_rest_users( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'fanimeta_harden_rest_users' );

/**
 * 设置内容宽度
 */
function fanimeta_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'fanimeta_content_width', 800 );
}
add_action( 'after_setup_theme', 'fanimeta_content_width', 0 );

/**
 * 注册侧边栏
 */
function fanimeta_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( '侧边栏', 'fanimeta' ),
			'id'            => 'sidebar-1',
			'description'   => __( '显示在内容区左侧的个人侧边栏。', 'fanimeta' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( '页脚', 'fanimeta' ),
			'id'            => 'footer-1',
			'description'   => __( '显示在页脚的部件区域。', 'fanimeta' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'fanimeta_widgets_init' );

/**
 * 隐藏侧边栏中的「近期文章」「近期评论」小工具。
 * 资源分享站不需要这两块，运行时通过 sidebars_widgets 过滤器移除（不改数据库，删除本过滤器即可恢复）。
 */
function fanimeta_hide_sidebar_widgets( $sidebars_widgets ) {
	if ( empty( $sidebars_widgets['sidebar-1'] ) ) {
		return $sidebars_widgets;
	}

	$widget_block = get_option( 'widget_block' );

	foreach ( $sidebars_widgets['sidebar-1'] as $key => $widget_id ) {
		// 仅处理块小工具（widget_block）
		if ( 0 !== strpos( $widget_id, 'block-' ) ) {
			continue;
		}

		$block_id = (int) substr( $widget_id, 6 );
		if ( empty( $widget_block[ $block_id ]['content'] ) ) {
			continue;
		}

		$content = $widget_block[ $block_id ]['content'];
		// 隐藏「近期文章」与「近期评论」块
		if ( false !== strpos( $content, 'latest-posts' ) || false !== strpos( $content, 'latest-comments' ) ) {
			unset( $sidebars_widgets['sidebar-1'][ $key ] );
		}
	}

	$sidebars_widgets['sidebar-1'] = array_values( $sidebars_widgets['sidebar-1'] );

	return $sidebars_widgets;
}
add_filter( 'sidebars_widgets', 'fanimeta_hide_sidebar_widgets' );

/**
 * 加载样式与脚本
 */
function fanimeta_scripts() {
	// 主样式
	wp_enqueue_style( 'fanimeta-style', get_stylesheet_uri(), array(), FANIMETA_VERSION );

	// Lato 字体（与 yuc.wiki 使用相同的国内镜像源）
	wp_enqueue_style(
		'fanimeta-fonts',
		'https://fonts.loli.net/css?family=Lato:300,300italic,400,400italic,700,700italic&display=swap',
		array(),
		null
	);

	// 站酷快乐体（卡通标题字体，国内镜像源）
	wp_enqueue_style(
		'fanimeta-comic-font',
		'https://fonts.loli.net/css?family=ZCOOL+KuaiLe&display=swap',
		array(),
		null
	);

	// 主题脚本
	wp_enqueue_script( 'fanimeta-script', get_template_directory_uri() . '/assets/js/main.js', array(), FANIMETA_VERSION, true );

	// 评论回复
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'fanimeta_scripts' );

/**
 * 站点统计：已发布文章数
 */
function fanimeta_post_count() {
	$count = wp_count_posts();
	return isset( $count->publish ) ? (int) $count->publish : 0;
}

/**
 * 站点统计：分类数
 */
function fanimeta_category_count() {
	$count = wp_count_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
		)
	);
	return is_wp_error( $count ) ? 0 : (int) $count;
}

/**
 * 站点统计：标签数
 */
function fanimeta_tag_count() {
	$count = wp_count_terms(
		array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => true,
		)
	);
	return is_wp_error( $count ) ? 0 : (int) $count;
}

/**
 * 获取文章阅读时间（分钟）
 */
function fanimeta_reading_time() {
	$content = get_post_field( 'post_content', get_the_ID() );
	$count   = mb_strlen( wp_strip_all_tags( $content ) );
	// 中文按每分钟 400 字估算
	$minutes = max( 1, (int) ceil( $count / 400 ) );
	return $minutes;
}

/**
 * 为 body 添加 class
 */
function fanimeta_body_classes( $classes ) {
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}
	return $classes;
}
add_filter( 'body_class', 'fanimeta_body_classes' );

/**
 * 摘要长度
 */
function fanimeta_excerpt_length( $length ) {
	return 55;
}
add_filter( 'excerpt_length', 'fanimeta_excerpt_length' );

/**
 * 摘要结尾省略号
 */
function fanimeta_excerpt_more( $more ) {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'fanimeta_excerpt_more' );

/**
 * 列表页摘要按字符数精简（中文友好）：点开文章前不显示完整内容。
 */
function fanimeta_excerpt_trim_chars( $excerpt ) {
	if ( is_admin() ) {
		return $excerpt;
	}
	$excerpt = wp_strip_all_tags( $excerpt );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $excerpt, 'UTF-8' ) > 36 ) {
		$excerpt = mb_substr( $excerpt, 0, 36, 'UTF-8' ) . '…';
	}
	return $excerpt;
}
add_filter( 'wp_trim_excerpt', 'fanimeta_excerpt_trim_chars', 20 );

/**
 * 登录/注册页标题文字改为站点名
 */
function fanimeta_login_headertext() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'fanimeta_login_headertext' );

/**
 * 登录/注册页 logo 链接指向站点首页
 */
function fanimeta_login_headerurl() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'fanimeta_login_headerurl' );

/**
 * 自定义 WordPress 登录/注册页外观，与主题黄色风格统一
 */
function fanimeta_login_styles() {
	$bg        = '#faf6e8';
	$card      = '#ffffff';
	$primary   = '#f7b500';
	$primary_d = '#d99e00';
	$accent    = '#a67c00';
	$text      = '#555555';
	$border    = '#e0d9c3';

	echo '<style type="text/css">
		body.login {
			background: ' . $bg . ';
		}
		body.login div#login {
			width: 340px;
		}
		body.login div#login h1 {
			margin-bottom: 18px;
		}
		body.login div#login h1 a {
			background-image: none;
			text-indent: 0;
			width: auto;
			height: auto;
			display: block;
			margin: 0 auto;
			font-size: 30px;
			font-weight: 700;
			line-height: 1.2;
			color: ' . $primary_d . ';
			text-decoration: none;
		}
		body.login div#login form {
			background: ' . $card . ';
			border: none;
			border-radius: 8px;
			box-shadow: 0 2px 2px 0 rgba(0,0,0,.12), 0 3px 1px -2px rgba(0,0,0,.06), 0 1px 5px 0 rgba(0,0,0,.12);
			padding: 30px 28px;
			margin-top: 0;
		}
		body.login div#login form label {
			color: ' . $text . ';
			font-size: 14px;
		}
		body.login div#login form .input {
			border: 1px solid ' . $border . ';
			border-radius: 4px;
			background: #fdfcf7;
			color: ' . $text . ';
			font-size: 16px;
			padding: 8px 10px;
			margin-top: 4px;
			min-height: 26px;
		}
		body.login div#login form .input:focus {
			border-color: ' . $primary . ';
			box-shadow: 0 0 0 2px rgba(247,181,0,.18);
		}
		body.login div#login form .button-primary,
		body.login .button.button-primary {
			background: ' . $primary . ';
			border: none;
			border-radius: 4px;
			color: #fff;
			text-shadow: none;
			box-shadow: none;
			font-weight: 600;
			font-size: 15px;
			height: 42px;
			line-height: 42px;
			padding: 0 18px;
			float: none;
			width: 100%;
			margin-top: 8px;
		}
		body.login div#login form .button-primary:hover,
		body.login .button.button-primary:hover {
			background: ' . $primary_d . ';
		}
		body.login #login #nav,
		body.login #login #backtoblog {
			text-align: center;
			margin-top: 14px;
			padding: 0;
		}
		body.login #login #nav a,
		body.login #login #backtoblog a {
			color: ' . $accent . ';
		}
		body.login #login #nav a:hover,
		body.login #login #backtoblog a:hover {
			color: ' . $primary_d . ';
		}
		body.login #login_error,
		body.login .message {
			border-left: 4px solid ' . $primary . ';
			border-radius: 4px;
			box-shadow: none;
			background: #fff;
		}
		body.login div#login form p.forgetmenot {
			margin-top: 14px;
		}
		body.login div#login form p.forgetmenot input[type=checkbox] {
			border-color: ' . $border . ';
		}
		body.login div#login form p.forgetmenot input[type=checkbox]:checked {
			background: ' . $primary . ';
			border-color: ' . $primary . ';
		}
		body.login .language-switcher {
			background: ' . $card . ';
			border: 1px solid ' . $border . ';
			border-radius: 4px;
		}
		/* 注册页：邮箱验证码 */
		body.login #fanimeta-send-code.button {
			display: block;
			width: 100%;
			height: 38px;
			line-height: 36px;
			margin: 8px 0 0;
			padding: 0 14px;
			float: none;
			font-size: 14px;
			font-weight: 700;
			color: #3d3320;
			background: ' . $primary . ';
			border: 1px solid ' . $primary_d . ';
			border-radius: 4px;
			box-shadow: none;
			text-shadow: none;
			cursor: pointer;
			transition: background .15s ease;
		}
		body.login #fanimeta-send-code.button:hover {
			background: ' . $primary_d . ';
			border-color: ' . $accent . ';
			color: #2f2819;
		}
		body.login #fanimeta-send-code.button:disabled,
		body.login #fanimeta-send-code.button.is-disabled {
			background: #ded7c2;
			border-color: #ded7c2;
			color: #8b8368;
			cursor: not-allowed;
		}
		body.login .fanimeta-code-tip {
			display: block;
			margin: 6px 0 8px;
			font-size: 12px;
			line-height: 1.6;
		}
		body.login .fanimeta-code-tip.is-ok {
			color: #1a7f37;
		}
		body.login .fanimeta-code-tip.is-error {
			color: #b32d2e;
		}
	</style>';
}
add_action( 'login_enqueue_scripts', 'fanimeta_login_styles' );

/**
 * 后台美化：加载黄色漫画卡通风样式表
 */
function fanimeta_admin_styles() {
	// 后台样式
	wp_enqueue_style( 'fanimeta-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), FANIMETA_VERSION );
}
add_action( 'admin_enqueue_scripts', 'fanimeta_admin_styles' );

/**
 * 后台「用户认证」管理菜单：管理员可一键给予/取消用户认证（绿色C标）。
 */
function fanimeta_verify_menu() {
	add_menu_page(
		__( '用户认证管理', 'fanimeta' ),
		__( '用户认证', 'fanimeta' ),
		'manage_options',
		'fanimeta-verify',
		'fanimeta_verify_page_render',
		'dashicons-awards',
		71
	);
}
add_action( 'admin_menu', 'fanimeta_verify_menu' );

/**
 * 渲染用户认证管理页面。
 */
function fanimeta_verify_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限访问', 'fanimeta' ) );
	}

	$fanimeta_msg    = isset( $_GET['fanimeta_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['fanimeta_msg'] ) ) : '';
	$fanimeta_target = isset( $_GET['fanimeta_target'] ) ? (int) $_GET['fanimeta_target'] : 0;
	$fanimeta_users  = get_users(
		array(
			'orderby' => 'ID',
			'order'   => 'ASC',
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '用户认证管理', 'fanimeta' ); ?></h1>

		<?php if ( $fanimeta_msg ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				if ( 'invite_generated' === $fanimeta_msg ) {
					$fanimeta_count = isset( $_GET['fanimeta_count'] ) ? (int) $_GET['fanimeta_count'] : 0;
					echo '已生成 ' . $fanimeta_count . ' 个新邀请码。';
				} else {
					echo 'granted' === $fanimeta_msg ? '已给予认证' : '已取消认证';
					$fanimeta_target_user = get_userdata( $fanimeta_target );
					if ( $fanimeta_target_user ) {
						/* translators: %s: 用户昵称 */
						echo '（用户：' . esc_html( $fanimeta_target_user->display_name ) . '）';
					}
				}
				?>
			</p></div>
		<?php endif; ?>

		<h2 style="margin-top:20px"><?php esc_html_e( '注册邀请码', 'fanimeta' ); ?></h2>
		<p>注册需填写邀请码，<strong>一个邀请码仅能注册一个账号</strong>（注册后自动失效）。点击下方按钮随机生成新邀请码，<strong>无法自定义</strong>。</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:14px">
			<input type="hidden" name="action" value="fanimeta_generate_invite_codes">
			<?php wp_nonce_field( 'fanimeta_generate_invite_codes' ); ?>
			<label><?php esc_html_e( '生成数量', 'fanimeta' ); ?>
				<input type="number" name="fanimeta_invite_count" value="5" min="1" max="100" style="width:80px">
			</label>
			<button type="submit" class="button button-primary"><?php esc_html_e( '生成邀请码', 'fanimeta' ); ?></button>
		</form>

		<?php $fanimeta_codes = fanimeta_get_invite_codes_raw(); ?>
		<table class="widefat striped" style="max-width:760px">
			<thead>
				<tr>
					<th style="width:260px"><?php esc_html_e( '邀请码', 'fanimeta' ); ?></th>
					<th><?php esc_html_e( '状态', 'fanimeta' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $fanimeta_codes ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( '暂无邀请码，请先生成。', 'fanimeta' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( array_reverse( $fanimeta_codes ) as $fanimeta_c ) :
						$fanimeta_code_str = isset( $fanimeta_c['code'] ) ? $fanimeta_c['code'] : '';
						$fanimeta_used_by  = isset( $fanimeta_c['used_by'] ) ? (int) $fanimeta_c['used_by'] : 0;
						?>
						<tr>
							<td><code style="font-size:13px"><?php echo esc_html( $fanimeta_code_str ); ?></code></td>
							<td>
								<?php if ( $fanimeta_used_by ) :
									$fanimeta_user = get_userdata( $fanimeta_used_by );
									$fanimeta_uname = $fanimeta_user ? $fanimeta_user->display_name : '用户#' . $fanimeta_used_by;
									echo '<span style="color:#999">已使用（' . esc_html( $fanimeta_uname ) . '）</span>';
								else :
									echo '<span style="color:#3aa93a;font-weight:600">未使用</span>';
								endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<hr style="margin:24px 0">

		<h2><?php esc_html_e( '用户认证列表', 'fanimeta' ); ?></h2>

		<p class="fanimeta-verify-desc">
			<span class="fanimeta-verify-demo"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/verify-badge.png' ); ?>" alt="" style="width:14px;vertical-align:-2px"> 站长</span>
			为固定认证（UID 1/2）；在此给予的认证显示为
			<span class="fanimeta-verify-demo"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/verify-badge-green.png' ); ?>" alt="" style="width:14px;vertical-align:-2px"> 认证用户</span>
			（绿色C标），叠加在该用户全站头像右下角。
		</p>

		<table class="widefat striped fanimeta-verify-table">
			<thead>
				<tr>
					<th style="width:56px">ID</th>
					<th style="width:60px">头像</th>
					<th>昵称 / 账号</th>
					<th style="width:150px">角色</th>
					<th style="width:150px">认证状态</th>
					<th style="width:130px">操作</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $fanimeta_users as $fanimeta_u ) :
					$fanimeta_is_owner    = fanimeta_is_owner( $fanimeta_u->ID );
					$fanimeta_is_verified = (bool) get_user_meta( $fanimeta_u->ID, 'fanimeta_verified', true );
					$fanimeta_toggle_url  = wp_nonce_url(
						admin_url( 'admin-post.php?action=fanimeta_toggle_verify&user_id=' . $fanimeta_u->ID ),
						'fanimeta_toggle_verify_' . $fanimeta_u->ID
					);
					?>
					<tr>
						<td><?php echo (int) $fanimeta_u->ID; ?></td>
						<td><?php echo get_avatar( $fanimeta_u->ID, 40 ); ?></td>
						<td>
							<strong><?php echo esc_html( $fanimeta_u->display_name ); ?></strong><br>
							<span class="fanimeta-verify-login">@<?php echo esc_html( $fanimeta_u->user_login ); ?></span>
						</td>
						<td><?php echo esc_html( implode( '、', $fanimeta_u->roles ) ); ?></td>
						<td>
							<?php if ( $fanimeta_is_owner ) : ?>
								<span style="color:#e8730c;font-weight:600">站长（橙色C标）</span>
							<?php elseif ( $fanimeta_is_verified ) : ?>
								<span style="color:#3aa93a;font-weight:600">已认证（绿色C标）</span>
							<?php else : ?>
								<span style="color:#999">未认证</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $fanimeta_is_owner ) : ?>
								<span style="color:#aaa">固定认证</span>
							<?php elseif ( $fanimeta_is_verified ) : ?>
								<a class="button" href="<?php echo esc_url( $fanimeta_toggle_url ); ?>">取消认证</a>
							<?php else : ?>
								<a class="button button-primary" href="<?php echo esc_url( $fanimeta_toggle_url ); ?>">给予认证</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * 处理给予/取消认证请求（仅管理员，带 nonce 校验）。
 */
function fanimeta_toggle_verify_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限操作', 'fanimeta' ) );
	}

	$fanimeta_user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
	check_admin_referer( 'fanimeta_toggle_verify_' . $fanimeta_user_id );

	$fanimeta_target = get_userdata( $fanimeta_user_id );
	if ( ! $fanimeta_target || fanimeta_is_owner( $fanimeta_user_id ) ) {
		wp_die( esc_html__( '无效的用户，或不可修改站长认证状态', 'fanimeta' ) );
	}

	if ( get_user_meta( $fanimeta_user_id, 'fanimeta_verified', true ) ) {
		delete_user_meta( $fanimeta_user_id, 'fanimeta_verified' );
		$fanimeta_msg = 'revoked';
	} else {
		update_user_meta( $fanimeta_user_id, 'fanimeta_verified', 1 );
		$fanimeta_msg = 'granted';
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'           => 'fanimeta-verify',
				'fanimeta_msg'   => $fanimeta_msg,
				'fanimeta_target' => $fanimeta_user_id,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_fanimeta_toggle_verify', 'fanimeta_toggle_verify_handler' );

/**
 * 后台「数据库」管理菜单：查看数据表、浏览数据、执行 SQL 查询。
 */
function fanimeta_database_menu() {
	add_menu_page(
		__( '数据库管理', 'fanimeta' ),
		__( '数据库', 'fanimeta' ),
		'manage_options',
		'fanimeta-database',
		'fanimeta_database_page_render',
		'dashicons-database',
		72
	);
}
add_action( 'admin_menu', 'fanimeta_database_menu' );

/**
 * 记录一条后台 SQL 写操作审计（最多保留最近 50 条）。
 *
 * 目的不是"防住"管理员 —— 而是让"谁在什么时候执行了什么"有据可查：
 * 一旦会话被劫持后发生破坏性操作，至少能定位时间点与来源 IP。
 *
 * @param string $sql      执行的语句。
 * @param int    $affected 影响行数。
 * @param string $error    数据库错误（如有）。
 */
function fanimeta_db_log_audit( $sql, $affected, $error = '' ) {
	$fanimeta_log = get_option( 'fanimeta_sql_audit', array() );
	if ( ! is_array( $fanimeta_log ) ) {
		$fanimeta_log = array();
	}

	$fanimeta_user = wp_get_current_user();

	array_unshift(
		$fanimeta_log,
		array(
			'time'     => current_time( 'mysql' ),
			'user'     => $fanimeta_user ? $fanimeta_user->user_login : '-',
			'ip'       => fanimeta_client_ip(),
			'sql'      => mb_substr( preg_replace( '/\s+/', ' ', (string) $sql ), 0, 300 ),
			'affected' => (int) $affected,
			'error'    => (string) $error,
		)
	);

	// autoload=false：这份日志只在数据库页读取，没必要每次请求都加载。
	update_option( 'fanimeta_sql_audit', array_slice( $fanimeta_log, 0, 50 ), false );
}

/**
 * 渲染数据库单元格内容：超长值截断并悬浮显示完整内容。
 *
 * @param mixed $value 单元格值。
 * @return string 安全的 HTML。
 */
function fanimeta_db_cell( $value ) {
	$fanimeta_cell = (string) $value;
	if ( strlen( $fanimeta_cell ) > 60 ) {
		$fanimeta_short = function_exists( 'mb_substr' ) ? mb_substr( $fanimeta_cell, 0, 60 ) : substr( $fanimeta_cell, 0, 60 );
		return '<span title="' . esc_attr( $fanimeta_cell ) . '">' . esc_html( $fanimeta_short ) . '…</span>';
	}
	return esc_html( $fanimeta_cell );
}

/**
 * 返回数据表对应的中文用途说明（用于后台数据库页，帮助识别表含义）。
 *
 * @param string $table 表名（如 wp_posts）。
 * @return string 中文用途说明，未知表返回空字符串。
 */
function fanimeta_table_purpose( $table ) {
	global $wpdb;
	$fanimeta_map = array(
		'posts'              => __( '文章 / 页面内容', 'fanimeta' ),
		'postmeta'           => __( '文章扩展信息（自定义字段）', 'fanimeta' ),
		'comments'           => __( '评论', 'fanimeta' ),
		'commentmeta'        => __( '评论扩展信息', 'fanimeta' ),
		'users'              => __( '用户账号', 'fanimeta' ),
		'usermeta'           => __( '用户扩展信息（认证、头像等）', 'fanimeta' ),
		'terms'              => __( '分类 / 标签术语', 'fanimeta' ),
		'term_taxonomy'      => __( '分类 / 标签归属', 'fanimeta' ),
		'term_relationships' => __( '文章与分类的关联', 'fanimeta' ),
		'termmeta'           => __( '术语扩展信息', 'fanimeta' ),
		'options'            => __( '站点设置', 'fanimeta' ),
		'links'              => __( '友情链接', 'fanimeta' ),
	);

	$fanimeta_prefix = $wpdb->prefix;
	if ( $fanimeta_prefix && 0 === strpos( $table, $fanimeta_prefix ) ) {
		$fanimeta_suffix = substr( $table, strlen( $fanimeta_prefix ) );
		return isset( $fanimeta_map[ $fanimeta_suffix ] ) ? $fanimeta_map[ $fanimeta_suffix ] : '';
	}
	return '';
}

/**
 * 渲染数据库管理页面。
 */
function fanimeta_database_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限访问', 'fanimeta' ) );
	}

	global $wpdb;

	$fanimeta_db_name   = defined( 'DB_NAME' ) ? DB_NAME : '';
	$fanimeta_mysql_ver = $wpdb->db_version();
	$fanimeta_tables    = $wpdb->get_col( 'SHOW TABLES' );

	// 当前查看的表（白名单校验，防 SQL 注入）。
	$fanimeta_current_table = isset( $_GET['table'] ) ? sanitize_text_field( wp_unslash( $_GET['table'] ) ) : '';
	if ( $fanimeta_current_table && ! in_array( $fanimeta_current_table, $fanimeta_tables, true ) ) {
		$fanimeta_current_table = '';
	}

	// 表数据分页参数。
	$fanimeta_per_page = 50;
	$fanimeta_paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

	// 读取当前表结构与数据。
	$fanimeta_columns = array();
	$fanimeta_rows    = array();
	$fanimeta_total   = 0;
	if ( $fanimeta_current_table ) {
		$fanimeta_columns = $wpdb->get_col( "DESC `{$fanimeta_current_table}`", 0 );
		$fanimeta_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$fanimeta_current_table}`" );
		$fanimeta_offset  = ( $fanimeta_paged - 1 ) * $fanimeta_per_page;
		$fanimeta_rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$fanimeta_current_table}` LIMIT %d, %d",
				$fanimeta_offset,
				$fanimeta_per_page
			),
			ARRAY_A
		);
	}

	// SQL 查询执行。
	$fanimeta_sql          = '';
	$fanimeta_sql_result   = null;
	$fanimeta_sql_columns  = array();
	$fanimeta_sql_affected = -1;
	$fanimeta_sql_is_write = false;
	$fanimeta_sql_error    = '';
	if ( isset( $_POST['fanimeta_sql_submit'] ) ) {
		check_admin_referer( 'fanimeta_db_query' );
		$fanimeta_sql = trim( (string) wp_unslash( $_POST['fanimeta_sql'] ) );
		if ( '' !== $fanimeta_sql ) {
			if ( preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $fanimeta_sql ) ) {
				$fanimeta_sql_result = $wpdb->get_results( $fanimeta_sql, ARRAY_A );
				if ( ! empty( $fanimeta_sql_result ) ) {
					$fanimeta_sql_columns = array_keys( $fanimeta_sql_result[0] );
				}
			} else {
				/*
				 * 写语句必须二次确认。
				 *
				 * 这个页面本质上是一个「任意 SQL 执行」入口：一旦管理员会话被
				 * XSS / CSRF / 第三方插件漏洞借道，攻击者可以一步拿到整库控制权
				 * （DROP 表、直接改写管理员密码哈希）；误操作同样不可回滚。
				 * 因此写操作要求显式输入确认词，并留下审计记录。
				 */
				$fanimeta_confirm = isset( $_POST['fanimeta_sql_confirm'] ) ? strtoupper( trim( (string) wp_unslash( $_POST['fanimeta_sql_confirm'] ) ) ) : '';

				if ( 'CONFIRM' !== $fanimeta_confirm ) {
					$fanimeta_sql_error = __( '写操作未执行：请在确认框中输入 CONFIRM 后再提交。', 'fanimeta' );
				} else {
					$fanimeta_sql_is_write = true;
					$fanimeta_sql_affected = (int) $wpdb->query( $fanimeta_sql );
					fanimeta_db_log_audit( $fanimeta_sql, $fanimeta_sql_affected, $wpdb->last_error );
				}
			}
			if ( $wpdb->last_error ) {
				$fanimeta_sql_error = $wpdb->last_error;
			}
		}
	}

	// 表行数（SHOW TABLE STATUS 的 Rows 为估算值）。
	$fanimeta_table_rows = array();
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $fanimeta_st ) {
		if ( isset( $fanimeta_st['Name'] ) ) {
			$fanimeta_table_rows[ $fanimeta_st['Name'] ] = isset( $fanimeta_st['Rows'] ) ? (int) $fanimeta_st['Rows'] : 0;
		}
	}

	// 文章内容速览：直接读取 wp_posts 中的文章，直观展示「文章内容存于数据库」。
	$fanimeta_posts       = array();
	$fanimeta_published   = 0;
	$fanimeta_post_detail = null;
	if ( in_array( $wpdb->posts, $fanimeta_tables, true ) ) {
		$fanimeta_posts = $wpdb->get_results(
			"SELECT ID, post_title, post_status, post_date, CHAR_LENGTH(post_content) AS content_len
			 FROM {$wpdb->posts}
			 WHERE post_type = 'post' AND post_status <> 'auto-draft'
			 ORDER BY post_date DESC",
			ARRAY_A
		);
		foreach ( $fanimeta_posts as $fanimeta_p ) {
			if ( 'publish' === $fanimeta_p['post_status'] ) {
				$fanimeta_published++;
			}
		}
		// 单篇文章全文查看。
		$fanimeta_post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( $fanimeta_post_id ) {
			$fanimeta_post_detail = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT ID, post_title, post_status, post_content FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post'",
					$fanimeta_post_id
				),
				ARRAY_A
			);
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '数据库管理', 'fanimeta' ); ?></h1>

		<div class="card" style="max-width:100%;margin-bottom:16px;">
			<h2><?php esc_html_e( '数据库概览', 'fanimeta' ); ?></h2>
			<p>
				<strong><?php esc_html_e( '数据库名：', 'fanimeta' ); ?></strong><?php echo esc_html( $fanimeta_db_name ); ?>
				&nbsp;|&nbsp;
				<strong><?php esc_html_e( 'MySQL 版本：', 'fanimeta' ); ?></strong><?php echo esc_html( $fanimeta_mysql_ver ); ?>
				&nbsp;|&nbsp;
				<strong><?php esc_html_e( '数据表数量：', 'fanimeta' ); ?></strong><?php echo count( $fanimeta_tables ); ?>
				&nbsp;|&nbsp;
				<strong><?php esc_html_e( '已发布文章：', 'fanimeta' ); ?></strong><?php echo (int) $fanimeta_published; ?><?php esc_html_e( ' 篇', 'fanimeta' ); ?>
			</p>
		</div>

		<div class="card" style="max-width:100%;margin-bottom:16px;">
			<h2><?php esc_html_e( '文章内容（存储于 wp_posts 表 post_content 字段）', 'fanimeta' ); ?></h2>
			<?php if ( empty( $fanimeta_posts ) ) : ?>
				<p><?php esc_html_e( '暂无文章数据。', 'fanimeta' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th style="width:60px;"><?php esc_html_e( 'ID', 'fanimeta' ); ?></th>
							<th><?php esc_html_e( '文章标题', 'fanimeta' ); ?></th>
							<th style="width:90px;"><?php esc_html_e( '状态', 'fanimeta' ); ?></th>
							<th style="width:110px;"><?php esc_html_e( '内容字数', 'fanimeta' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( '操作', 'fanimeta' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					$fanimeta_status_label = array(
						'publish' => __( '已发布', 'fanimeta' ),
						'draft'   => __( '草稿', 'fanimeta' ),
						'pending' => __( '待审', 'fanimeta' ),
						'private' => __( '私密', 'fanimeta' ),
						'trash'   => __( '回收站', 'fanimeta' ),
					);
					foreach ( $fanimeta_posts as $fanimeta_p ) :
						$fanimeta_status = isset( $fanimeta_status_label[ $fanimeta_p['post_status'] ] ) ? $fanimeta_status_label[ $fanimeta_p['post_status'] ] : $fanimeta_p['post_status'];
						?>
						<tr>
							<td><?php echo (int) $fanimeta_p['ID']; ?></td>
							<td><?php echo esc_html( $fanimeta_p['post_title'] ); ?></td>
							<td><?php echo esc_html( $fanimeta_status ); ?></td>
							<td><?php echo (int) $fanimeta_p['content_len']; ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'fanimeta-database', 'post_id' => $fanimeta_p['ID'] ) ) ); ?>"><?php esc_html_e( '查看全文', 'fanimeta' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $fanimeta_post_detail ) : ?>
				<h3 style="margin-top:16px;">
					<?php
					printf(
						/* translators: 1: 文章 ID, 2: 文章标题 */
						esc_html__( '文章 #%1$d「%2$s」的完整内容', 'fanimeta' ),
						(int) $fanimeta_post_detail['ID'],
						esc_html( $fanimeta_post_detail['post_title'] )
					);
					?>
				</h3>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 16px;max-height:480px;overflow:auto;font-family:monospace;white-space:pre-wrap;word-break:break-word;">
					<?php echo esc_html( $fanimeta_post_detail['post_content'] ); ?>
				</div>
				<p>
					<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=fanimeta-database' ) ); ?>"><?php esc_html_e( '收起全文', 'fanimeta' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<div class="card" style="max-width:100%;margin-bottom:16px;">
			<h2><?php esc_html_e( 'SQL 查询', 'fanimeta' ); ?></h2>
			<form method="post" action="" onsubmit="var s=(this.fanimeta_sql.value||'').replace(/^\s+/,'');if(!/^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i.test(s)){if((this.fanimeta_sql_confirm.value||'').toUpperCase()!=='CONFIRM'){alert('这是写操作，请在确认框中输入 CONFIRM 后再提交。');return false;}return confirm('这是写操作，将直接修改数据库且不可回滚。确认执行？');}">
				<?php wp_nonce_field( 'fanimeta_db_query' ); ?>
				<textarea name="fanimeta_sql" rows="3" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $fanimeta_sql ); ?></textarea>
				<p>
					<input type="text" name="fanimeta_sql_confirm" value="" autocomplete="off" placeholder="CONFIRM" style="width:130px;font-family:monospace;" />
					<input type="submit" name="fanimeta_sql_submit" class="button button-primary" value="<?php esc_attr_e( '执行查询', 'fanimeta' ); ?>" />
					<span class="description"><?php esc_html_e( '查询语句（SELECT / SHOW / DESCRIBE / EXPLAIN）直接执行；写操作（INSERT / UPDATE / DELETE / DROP 等）需在确认框输入 CONFIRM，且会记入下方审计日志。', 'fanimeta' ); ?></span>
				</p>
			</form>
			<?php if ( $fanimeta_sql_error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $fanimeta_sql_error ); ?></p></div>
			<?php elseif ( $fanimeta_sql_is_write ) : ?>
				<div class="notice notice-success"><p><?php printf( esc_html__( '执行成功，影响 %d 行。', 'fanimeta' ), $fanimeta_sql_affected ); ?></p></div>
			<?php elseif ( null !== $fanimeta_sql_result ) : ?>
				<div class="notice notice-success"><p><?php printf( esc_html__( '查询到 %d 行结果。', 'fanimeta' ), count( $fanimeta_sql_result ) ); ?></p></div>
				<table class="wp-list-table widefat striped" style="table-layout:fixed;word-break:break-all;">
					<thead><tr><?php foreach ( $fanimeta_sql_columns as $c ) { echo '<th>' . esc_html( $c ) . '</th>'; } ?></tr></thead>
					<tbody>
					<?php foreach ( $fanimeta_sql_result as $r ) : ?>
						<tr><?php foreach ( $r as $v ) { echo '<td>' . fanimeta_db_cell( $v ) . '</td>'; } ?></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<?php
		$fanimeta_audit = get_option( 'fanimeta_sql_audit', array() );
		if ( is_array( $fanimeta_audit ) && ! empty( $fanimeta_audit ) ) :
			?>
			<div class="card" style="max-width:100%;margin-bottom:16px;">
				<h2><?php esc_html_e( '写操作审计日志', 'fanimeta' ); ?></h2>
				<p class="description"><?php esc_html_e( '仅记录会修改数据的语句，最多保留最近 50 条。', 'fanimeta' ); ?></p>
				<table class="wp-list-table widefat striped" style="table-layout:fixed;word-break:break-all;">
					<thead>
						<tr>
							<th style="width:150px;"><?php esc_html_e( '时间', 'fanimeta' ); ?></th>
							<th style="width:110px;"><?php esc_html_e( '操作者', 'fanimeta' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( '来源 IP', 'fanimeta' ); ?></th>
							<th><?php esc_html_e( '语句', 'fanimeta' ); ?></th>
							<th style="width:90px;"><?php esc_html_e( '影响行数', 'fanimeta' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $fanimeta_audit as $fanimeta_a ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $fanimeta_a['time'] ) ? $fanimeta_a['time'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $fanimeta_a['user'] ) ? $fanimeta_a['user'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $fanimeta_a['ip'] ) ? $fanimeta_a['ip'] : '' ); ?></td>
							<td><code><?php echo esc_html( isset( $fanimeta_a['sql'] ) ? $fanimeta_a['sql'] : '' ); ?></code></td>
							<td><?php echo (int) ( isset( $fanimeta_a['affected'] ) ? $fanimeta_a['affected'] : 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( '数据表列表', 'fanimeta' ); ?></h2>
		<table class="wp-list-table widefat striped">
			<thead><tr><th><?php esc_html_e( '表名', 'fanimeta' ); ?></th><th><?php esc_html_e( '用途', 'fanimeta' ); ?></th><th><?php esc_html_e( '行数（约）', 'fanimeta' ); ?></th><th><?php esc_html_e( '操作', 'fanimeta' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $fanimeta_tables as $t ) : ?>
				<tr>
					<td><code><?php echo esc_html( $t ); ?></code></td>
					<td><?php echo esc_html( fanimeta_table_purpose( $t ) ); ?></td>
					<td><?php echo isset( $fanimeta_table_rows[ $t ] ) ? (int) $fanimeta_table_rows[ $t ] : '-'; ?></td>
					<td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'fanimeta-database', 'table' => $t ) ) ); ?>"><?php esc_html_e( '查看数据', 'fanimeta' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $fanimeta_current_table ) : ?>
			<h2>
				<?php printf( esc_html__( '数据表：%s', 'fanimeta' ), esc_html( $fanimeta_current_table ) ); ?>
				<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=fanimeta-database' ) ); ?>"><?php esc_html_e( '返回列表', 'fanimeta' ); ?></a>
			</h2>
			<p><?php printf( esc_html__( '共 %d 行，当前第 %d 页（每页 %d 行）。', 'fanimeta' ), $fanimeta_total, $fanimeta_paged, $fanimeta_per_page ); ?></p>
			<table class="wp-list-table widefat striped" style="table-layout:fixed;word-break:break-all;">
				<thead><tr><?php foreach ( $fanimeta_columns as $c ) { echo '<th>' . esc_html( $c ) . '</th>'; } ?></tr></thead>
				<tbody>
				<?php if ( empty( $fanimeta_rows ) ) : ?>
					<tr><td colspan="<?php echo max( 1, count( $fanimeta_columns ) ); ?>"><?php esc_html_e( '无数据。', 'fanimeta' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $fanimeta_rows as $r ) : ?>
						<tr><?php foreach ( $r as $v ) { echo '<td>' . fanimeta_db_cell( $v ) . '</td>'; } ?></tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<?php
			$fanimeta_total_pages = (int) ceil( $fanimeta_total / $fanimeta_per_page );
			if ( $fanimeta_total_pages > 1 ) :
				$fanimeta_base = add_query_arg( array( 'page' => 'fanimeta-database', 'table' => $fanimeta_current_table ) );
				echo '<p class="tablenav">';
				for ( $i = 1; $i <= $fanimeta_total_pages; $i++ ) {
					if ( $i === $fanimeta_paged ) {
						echo '<span class="button button-small" style="font-weight:bold;">' . (int) $i . '</span> ';
					} else {
						echo '<a class="button button-small" href="' . esc_url( add_query_arg( 'paged', $i, $fanimeta_base ) ) . '">' . (int) $i . '</a> ';
					}
				}
				echo '</p>';
			endif;
			?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * 邀请码数据结构：option fanimeta_invite_codes 存 JSON 数组，每项：
 *   code       => 邀请码字符串（随机生成，不可自定义）
 *   used_by    => 使用者用户 ID（0 表示未使用）
 *   used_at    => 使用时间
 *   created_at => 生成时间
 * 一个邀请码仅能被一个账号使用，注册后即失效。
 */

/**
 * 读取邀请码原始数据（兼容旧版多行文本格式并自动迁移）。
 */
function fanimeta_get_invite_codes_raw() {
	$fanimeta_stored = get_option( 'fanimeta_invite_codes', array() );
	if ( is_array( $fanimeta_stored ) ) {
		return $fanimeta_stored;
	}
	$fanimeta_stored = (string) $fanimeta_stored;
	if ( '' === $fanimeta_stored ) {
		return array();
	}
	$fanimeta_decoded = json_decode( $fanimeta_stored, true );
	if ( is_array( $fanimeta_decoded ) ) {
		return $fanimeta_decoded;
	}
	// 旧格式：多行文本，每个码视为一个「未使用」的邀请码
	$fanimeta_lines    = array_filter( array_map( 'trim', explode( "\n", $fanimeta_stored ) ) );
	$fanimeta_migrated = array();
	foreach ( $fanimeta_lines as $fanimeta_line ) {
		$fanimeta_migrated[] = array(
			'code'       => $fanimeta_line,
			'used_by'    => 0,
			'used_at'    => '',
			'created_at' => current_time( 'mysql' ),
		);
	}
	fanimeta_save_invite_codes_raw( $fanimeta_migrated );
	return $fanimeta_migrated;
}

/**
 * 保存邀请码数据（JSON 编码存储）。
 */
function fanimeta_save_invite_codes_raw( $fanimeta_codes ) {
	update_option( 'fanimeta_invite_codes', wp_json_encode( $fanimeta_codes ) );
}

/**
 * 生成一个随机邀请码：4 组各 4 位大写字母/数字，用连字符分隔。
 * 剔除易混淆字符 I、O、0、1。
 */
function fanimeta_generate_invite_code() {
	$fanimeta_chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$fanimeta_parts = array();
	for ( $g = 0; $g < 4; $g++ ) {
		$fanimeta_part = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$fanimeta_part .= $fanimeta_chars[ random_int( 0, strlen( $fanimeta_chars ) - 1 ) ];
		}
		$fanimeta_parts[] = $fanimeta_part;
	}
	return implode( '-', $fanimeta_parts );
}

/**
 * 查找邀请码条目（大小写不敏感，恒定时间比较）。
 */
function fanimeta_find_invite_entry( $fanimeta_code ) {
	$fanimeta_code = strtoupper( trim( (string) $fanimeta_code ) );
	foreach ( fanimeta_get_invite_codes_raw() as $fanimeta_entry ) {
		if ( isset( $fanimeta_entry['code'] ) && hash_equals( strtoupper( (string) $fanimeta_entry['code'] ), $fanimeta_code ) ) {
			return $fanimeta_entry;
		}
	}
	return null;
}

/**
 * 校验邀请码是否有效（存在且未被使用）。
 */
function fanimeta_invite_code_valid( $fanimeta_code ) {
	$fanimeta_entry = fanimeta_find_invite_entry( $fanimeta_code );
	if ( ! $fanimeta_entry ) {
		return false;
	}
	return empty( $fanimeta_entry['used_by'] );
}

/**
 * 标记邀请码已被某用户使用（注册成功后调用）。
 */
function fanimeta_consume_invite_code( $fanimeta_code, $fanimeta_user_id ) {
	$fanimeta_code  = strtoupper( trim( (string) $fanimeta_code ) );
	$fanimeta_codes = fanimeta_get_invite_codes_raw();
	foreach ( $fanimeta_codes as $fanimeta_i => $fanimeta_entry ) {
		if ( isset( $fanimeta_entry['code'] ) && hash_equals( strtoupper( (string) $fanimeta_entry['code'] ), $fanimeta_code ) && empty( $fanimeta_entry['used_by'] ) ) {
			$fanimeta_codes[ $fanimeta_i ]['used_by'] = (int) $fanimeta_user_id;
			$fanimeta_codes[ $fanimeta_i ]['used_at'] = current_time( 'mysql' );
			fanimeta_save_invite_codes_raw( $fanimeta_codes );
			return true;
		}
	}
	return false;
}

/**
 * 注册邮箱验证码总开关。
 *
 * 置为 true：注册页显示「邮箱验证码 + 获取验证码」，提交时校验验证码。
 * 置为 false：整块隐藏并跳过校验（发信通道不可用时可作为降级手段）。
 *
 * 发信通道：阿里云邮件推送 DirectMail（发信地址需先在控制台完成域名验证）。
 * 也可在 wp-config.php 中先行定义同名常量覆盖（因下方有 defined() 保护）。
 */
if ( ! defined( 'FANIMETA_REG_EMAIL_VERIFY' ) ) {
	define( 'FANIMETA_REG_EMAIL_VERIFY', true );
}

/**
 * 当前是否启用「注册邮箱验证码」。
 */
function fanimeta_register_email_verify_enabled() {
	return (bool) apply_filters( 'fanimeta_register_email_verify_enabled', FANIMETA_REG_EMAIL_VERIFY );
}

/**
 * 在 WordPress 原生注册表单中追加「密码」「邀请码」字段。
 * 字段顺序：账号昵称 → 账号邮箱 → 密码 → 确认密码 → 邀请码
 * （启用邮箱验证码时为：账号昵称 → 账号邮箱 → 密码 → 确认密码 → 邮箱验证码 → 邀请码）
 */
function fanimeta_register_form_extra() {
	$fanimeta_code       = isset( $_POST['fanimeta_invite_code'] ) ? trim( wp_unslash( $_POST['fanimeta_invite_code'] ) ) : '';
	$fanimeta_email_code = isset( $_POST['fanimeta_email_code'] ) ? trim( wp_unslash( $_POST['fanimeta_email_code'] ) ) : '';
	?>
	<p>
		<label for="fanimeta_password"><?php esc_html_e( '密码', 'fanimeta' ); ?><br>
			<input type="password" name="fanimeta_password" id="fanimeta_password" class="input" value="" size="25" autocomplete="new-password">
		</label>
	</p>
	<p>
		<label for="fanimeta_password_confirm"><?php esc_html_e( '确认密码', 'fanimeta' ); ?><br>
			<input type="password" name="fanimeta_password_confirm" id="fanimeta_password_confirm" class="input" value="" size="25" autocomplete="new-password">
		</label>
	</p>
	<?php if ( fanimeta_register_email_verify_enabled() ) : ?>
	<p>
		<label for="fanimeta_email_code"><?php esc_html_e( '邮箱验证码', 'fanimeta' ); ?><br>
			<input type="text" name="fanimeta_email_code" id="fanimeta_email_code" class="input" value="<?php echo esc_attr( $fanimeta_email_code ); ?>" size="25" autocomplete="off" inputmode="numeric" maxlength="6" placeholder="<?php esc_attr_e( '6 位数字', 'fanimeta' ); ?>">
		</label>
		<button type="button" class="button fanimeta-send-code" id="fanimeta-send-code"><?php esc_html_e( '获取验证码', 'fanimeta' ); ?></button>
		<span class="fanimeta-code-tip" id="fanimeta-code-tip" aria-live="polite"></span>
	</p>
	<?php endif; ?>
	<p>
		<label for="fanimeta_invite_code"><?php esc_html_e( '邀请码', 'fanimeta' ); ?><br>
			<input type="text" name="fanimeta_invite_code" id="fanimeta_invite_code" class="input" value="<?php echo esc_attr( $fanimeta_code ); ?>" size="25" autocomplete="off">
		</label>
	</p>
	<?php
}
add_action( 'register_form', 'fanimeta_register_form_extra' );

/* ==========================================================================
 * 密码强度
 * ========================================================================== */

/**
 * 常见弱口令表（均为小写）。
 *
 * 不追求"十万条字典"那种重型方案（那是专业插件的活），
 * 只挡掉撞库脚本会优先尝试的那一批 —— 收益/成本比最高的一段。
 *
 * @return array
 */
function fanimeta_common_passwords() {
	return array(
		'123456', '123456789', '12345678', '1234567890', '111111', '000000',
		'666666', '888888', '123123', '123321', '112233', '654321',
		'abc123', 'abcd1234', 'abc123456', 'a123456', 'a1234567', 'a123456789',
		'qwerty', 'qwerty123', 'qwe123456', '1q2w3e4r', '1qaz2wsx', 'qazwsx',
		'zxcvbnm', 'asdfghjkl', 'password', 'password1', 'passw0rd',
		'admin', 'admin123', 'admin888', 'administrator', 'root', 'root123',
		'test123', 'guest123', 'qq123456', 'aa123456', 'aa123456789',
		'1234567', '123456a', '123qwe', '5201314', '1314520', 'iloveyou',
		'woaini', 'woaini1314', 'woaini520', 'mima123', 'xiaocheng',
		'fanimeta', 'xiaomi123', 'huawei123', 'taobao123', 'weixin123',
	);
}

/**
 * 校验密码强度。
 *
 * 注册与重置密码共用同一个函数 —— 否则"注册严、重置松"会成为绕过通道。
 *
 * @param string $password 明文密码。
 * @param string $login    登录名（可选，禁止密码包含昵称）。
 * @param string $email    邮箱（可选，禁止密码包含邮箱）。
 * @return true|WP_Error
 */
function fanimeta_password_check( $password, $login = '', $email = '' ) {
	$password = (string) $password;
	$min      = (int) FANIMETA_PASSWORD_MIN_LEN;

	if ( '' === $password ) {
		return new WP_Error( 'fanimeta_pass_empty', '请设置密码。' );
	}

	if ( strlen( $password ) < $min ) {
		return new WP_Error( 'fanimeta_pass_short', '密码至少 ' . $min . ' 位。' );
	}

	if ( strlen( $password ) > 128 ) {
		return new WP_Error( 'fanimeta_pass_long', '密码过长，请控制在 128 位以内。' );
	}

	if ( ! preg_match( '/[A-Za-z]/', $password ) || ! preg_match( '/[0-9]/', $password ) ) {
		return new WP_Error( 'fanimeta_pass_kind', '密码需同时包含字母和数字。' );
	}

	// 单一字符重复，例如 aaaaaaaaaa
	if ( preg_match( '/^(.)\1+$/u', $password ) ) {
		return new WP_Error( 'fanimeta_pass_repeat', '密码不能是同一个字符的重复。' );
	}

	$lower = strtolower( $password );
	if ( in_array( $lower, fanimeta_common_passwords(), true ) ) {
		return new WP_Error( 'fanimeta_pass_common', '这个密码太常见了，容易被猜到，请换一个。' );
	}

	$login = strtolower( trim( (string) $login ) );
	if ( strlen( $login ) >= 3 && false !== strpos( $lower, $login ) ) {
		return new WP_Error( 'fanimeta_pass_in_login', '密码不能包含你的昵称。' );
	}

	$email = strtolower( trim( (string) $email ) );
	$local = ( false !== strpos( $email, '@' ) ) ? strstr( $email, '@', true ) : $email;
	if ( strlen( $local ) >= 3 && false !== strpos( $lower, $local ) ) {
		return new WP_Error( 'fanimeta_pass_in_email', '密码不能包含你的邮箱。' );
	}

	return true;
}

/**
 * 注册时校验：
 * - 昵称：不能重复、不能纯数字、只能中英文数字（不可含特殊符号）
 * - 密码：必填、至少 FANIMETA_PASSWORD_MIN_LEN 位、含字母与数字、非弱口令、两次一致
 * - 邮箱验证码：仅当 FANIMETA_REG_EMAIL_VERIFY 为 true 时校验
 * - 邀请码：必填且未被使用
 */
function fanimeta_register_check_fields( $fanimeta_errors, $fanimeta_login, $fanimeta_email ) {
	// 昵称原始输入（sanitize_user 会静默删除特殊符号，故必须读原始值检测）
	$fanimeta_nickname_raw = isset( $_POST['user_login'] ) ? trim( (string) wp_unslash( $_POST['user_login'] ) ) : '';

	// 1. 不可含特殊符号（只允许中文、英文、数字）
	if ( '' !== $fanimeta_nickname_raw && ! preg_match( '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u', $fanimeta_nickname_raw ) ) {
		$fanimeta_errors->add( 'fanimeta_nickname_invalid', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html__( '昵称只能包含中文、英文或数字，不能包含特殊符号。', 'fanimeta' ) );
	}

	// 2. 不可纯数字
	if ( '' !== $fanimeta_nickname_raw && preg_match( '/^[0-9]+$/', $fanimeta_nickname_raw ) ) {
		$fanimeta_errors->add( 'fanimeta_nickname_numeric', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html__( '昵称不能为纯数字。', 'fanimeta' ) );
	}

	// 3. 不可重复（WordPress 原生会报 username_exists，这里补充提示且避免重复报错）
	//    文案刻意与「邮箱已注册」保持同一句 —— 否则注册页可被脚本用来
	//    批量筛选出「有效昵称 + 有效邮箱」清单，直接喂给撞库与定向钓鱼。
	if ( '' !== $fanimeta_login && username_exists( $fanimeta_login ) && ! $fanimeta_errors->get_error_messages( 'username_exists' ) ) {
		$fanimeta_errors->add( 'fanimeta_nickname_exists', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html__( '该昵称或邮箱当前不可用，请更换后重试。', 'fanimeta' ) );
	}

	$fanimeta_password         = isset( $_POST['fanimeta_password'] ) ? (string) wp_unslash( $_POST['fanimeta_password'] ) : '';
	$fanimeta_password_confirm = isset( $_POST['fanimeta_password_confirm'] ) ? (string) wp_unslash( $_POST['fanimeta_password_confirm'] ) : '';

	$fanimeta_password_result = fanimeta_password_check( $fanimeta_password, $fanimeta_login, $fanimeta_email );
	if ( is_wp_error( $fanimeta_password_result ) ) {
		$fanimeta_errors->add( 'fanimeta_password', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html( $fanimeta_password_result->get_error_message() ) );
	} elseif ( $fanimeta_password !== $fanimeta_password_confirm ) {
		$fanimeta_errors->add( 'fanimeta_password_mismatch', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html__( '两次输入的密码不一致，请重新输入。', 'fanimeta' ) );
	}

	// 邮箱验证码：确认邮箱真实可用（防乱填 / 冒用他人邮箱）。开关关闭时跳过校验。
	if ( fanimeta_register_email_verify_enabled() ) {
		$fanimeta_email_code = isset( $_POST['fanimeta_email_code'] ) ? trim( wp_unslash( $_POST['fanimeta_email_code'] ) ) : '';
		$fanimeta_code_check = fanimeta_email_check_code( $fanimeta_email, $fanimeta_email_code );
		if ( is_wp_error( $fanimeta_code_check ) ) {
			$fanimeta_errors->add( 'fanimeta_email_code', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html( $fanimeta_code_check->get_error_message() ) );
		}
	}

	$fanimeta_code = isset( $_POST['fanimeta_invite_code'] ) ? trim( wp_unslash( $_POST['fanimeta_invite_code'] ) ) : '';
	if ( '' === $fanimeta_code ) {
		$fanimeta_errors->add( 'fanimeta_invite_empty', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html__( '请填写邀请码。', 'fanimeta' ) );
	} elseif ( ! fanimeta_invite_code_valid( $fanimeta_code ) ) {
		$fanimeta_errors->add( 'fanimeta_invite_invalid', '<strong>' . esc_html__( '错误', 'fanimeta' ) . '</strong>：' . esc_html__( '邀请码无效或已被使用。', 'fanimeta' ) );
	}

	return $fanimeta_errors;
}
add_filter( 'registration_errors', 'fanimeta_register_check_fields', 10, 3 );

/**
 * 处理后台「生成邀请码」请求（仅管理员，nonce 校验）。
 * 随机生成指定数量的邀请码并追加到列表中。
 */
function fanimeta_generate_invite_codes_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限操作', 'fanimeta' ) );
	}
	check_admin_referer( 'fanimeta_generate_invite_codes' );

	$fanimeta_count = isset( $_POST['fanimeta_invite_count'] ) ? (int) $_POST['fanimeta_invite_count'] : 5;
	$fanimeta_count = max( 1, min( 100, $fanimeta_count ) );

	$fanimeta_codes = fanimeta_get_invite_codes_raw();
	for ( $i = 0; $i < $fanimeta_count; $i++ ) {
		$fanimeta_codes[] = array(
			'code'       => fanimeta_generate_invite_code(),
			'used_by'    => 0,
			'used_at'    => '',
			'created_at' => current_time( 'mysql' ),
		);
	}
	fanimeta_save_invite_codes_raw( $fanimeta_codes );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'           => 'fanimeta-verify',
				'fanimeta_msg'   => 'invite_generated',
				'fanimeta_count' => $fanimeta_count,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_fanimeta_generate_invite_codes', 'fanimeta_generate_invite_codes_handler' );

/**
 * 注册成功后：设置用户密码，并消耗其所填邀请码（标记为已使用）。
 * 优先级 5，先于自动登录（优先级 10）执行。
 */
function fanimeta_register_after_setup( $fanimeta_user_id ) {
	$fanimeta_password = isset( $_POST['fanimeta_password'] ) ? (string) wp_unslash( $_POST['fanimeta_password'] ) : '';
	if ( '' !== $fanimeta_password ) {
		wp_set_password( $fanimeta_password, $fanimeta_user_id );
	}

	$fanimeta_code = isset( $_POST['fanimeta_invite_code'] ) ? trim( wp_unslash( $_POST['fanimeta_invite_code'] ) ) : '';
	if ( '' !== $fanimeta_code ) {
		fanimeta_consume_invite_code( $fanimeta_code, $fanimeta_user_id );
	}

	// 注册成功即作废邮箱验证码，保证一次性有效
	$fanimeta_user = get_userdata( $fanimeta_user_id );
	if ( $fanimeta_user && ! empty( $fanimeta_user->user_email ) ) {
		fanimeta_email_clear_code( $fanimeta_user->user_email );
	}
}
add_action( 'user_register', 'fanimeta_register_after_setup', 5 );

/**
 * 注册页字段文案：将原生「用户名 / 邮箱」改为「账号昵称 / 账号邮箱」。
 */
function fanimeta_register_labels( $fanimeta_translation, $fanimeta_text, $fanimeta_domain ) {
	if ( 'default' !== $fanimeta_domain ) {
		return $fanimeta_translation;
	}
	if ( ! isset( $_GET['action'] ) || 'register' !== $_GET['action'] ) {
		return $fanimeta_translation;
	}
	if ( 'Username' === $fanimeta_text ) {
		return '账号昵称';
	}
	if ( 'Email' === $fanimeta_text ) {
		return '账号邮箱';
	}
	return $fanimeta_translation;
}
add_filter( 'gettext', 'fanimeta_register_labels', 10, 3 );

/* ==========================================================================
 * 邮件服务（SMTP 发信 + 注册邮箱验证码）
 * --------------------------------------------------------------------------
 * SMTP 账号与授权码统一写在 wp-config.php，不落库，
 * 避免网站数据库导出（交作业转储 SQL）时把授权码一起带出去。
 * ========================================================================== */

/** 验证码有效期（秒） */
if ( ! defined( 'FANIMETA_EMAIL_CODE_TTL' ) ) {
	define( 'FANIMETA_EMAIL_CODE_TTL', 300 );
}

/** 同一邮箱两次发送之间的最小间隔（秒） */
if ( ! defined( 'FANIMETA_EMAIL_CODE_COOLDOWN' ) ) {
	define( 'FANIMETA_EMAIL_CODE_COOLDOWN', 60 );
}

/** 单个验证码允许输错的最大次数 */
if ( ! defined( 'FANIMETA_EMAIL_CODE_MAX_TRY' ) ) {
	define( 'FANIMETA_EMAIL_CODE_MAX_TRY', 5 );
}

/**
 * 是否已在 wp-config.php 中配置 SMTP 账号。
 *
 * @return bool
 */
function fanimeta_smtp_configured() {
	$has_user = defined( 'FANIMETA_SMTP_USER' ) && '' !== trim( (string) FANIMETA_SMTP_USER );
	$has_pass = defined( 'FANIMETA_SMTP_PASS' ) && '' !== trim( (string) FANIMETA_SMTP_PASS );
	return $has_user && $has_pass;
}

/**
 * 用 wp-config.php 常量接管 PHPMailer，改为走 SMTP 投递。
 * （阿里云 ECS 默认封禁 25 端口，且 PHP mail() 依赖本机 sendmail，实际发不出去。）
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer 实例。
 */
function fanimeta_smtp_init( $phpmailer ) {
	if ( ! fanimeta_smtp_configured() ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host        = defined( 'FANIMETA_SMTP_HOST' ) ? FANIMETA_SMTP_HOST : 'smtp.qq.com';
	$phpmailer->Port        = defined( 'FANIMETA_SMTP_PORT' ) ? (int) FANIMETA_SMTP_PORT : 465;
	$phpmailer->SMTPAuth    = true;
	$phpmailer->Username    = FANIMETA_SMTP_USER;
	$phpmailer->Password    = FANIMETA_SMTP_PASS;
	$phpmailer->SMTPSecure  = defined( 'FANIMETA_SMTP_SECURE' ) ? FANIMETA_SMTP_SECURE : 'ssl';
	$phpmailer->SMTPAutoTLS = false;
	$phpmailer->Timeout     = 20;
	$phpmailer->CharSet     = 'UTF-8';
}
add_action( 'phpmailer_init', 'fanimeta_smtp_init' );

/**
 * 发件人邮箱：QQ/163 等邮箱要求发件人必须与登录账号一致，否则报 550。
 *
 * @param string $email 默认发件人。
 * @return string
 */
function fanimeta_mail_from( $email ) {
	if ( defined( 'FANIMETA_SMTP_FROM' ) && '' !== trim( (string) FANIMETA_SMTP_FROM ) ) {
		return FANIMETA_SMTP_FROM;
	}
	if ( defined( 'FANIMETA_SMTP_USER' ) && '' !== trim( (string) FANIMETA_SMTP_USER ) ) {
		return FANIMETA_SMTP_USER;
	}
	return $email;
}
add_filter( 'wp_mail_from', 'fanimeta_mail_from', 99 );

/**
 * 发件人显示名称。
 *
 * @param string $name 默认名称。
 * @return string
 */
function fanimeta_mail_from_name( $name ) {
	if ( defined( 'FANIMETA_SMTP_FROM_NAME' ) && '' !== trim( (string) FANIMETA_SMTP_FROM_NAME ) ) {
		return FANIMETA_SMTP_FROM_NAME;
	}
	return get_bloginfo( 'name' );
}
add_filter( 'wp_mail_from_name', 'fanimeta_mail_from_name', 99 );

/* ==========================================================================
 * 发信配额与通用限流
 * ========================================================================== */

/**
 * 取客户端 IP。
 *
 * 只信任 REMOTE_ADDR —— X-Forwarded-For 可被客户端随意伪造，
 * 拿它做限流等于把限流开关交给攻击者。
 *
 * @return string
 */
function fanimeta_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
	return ( '' !== $ip ) ? $ip : 'unknown';
}

/**
 * 全站每日发信额度：transient 键名。
 *
 * @return string
 */
function fanimeta_mail_quota_day_key() {
	return 'fanimeta_mail_day_' . gmdate( 'Ymd' );
}

/**
 * 今日已用发信额度。
 *
 * @return int
 */
function fanimeta_mail_quota_day_used() {
	return (int) get_transient( fanimeta_mail_quota_day_key() );
}

/**
 * 今日发信额度是否已用尽。
 *
 * @return bool
 */
function fanimeta_mail_quota_day_full() {
	return fanimeta_mail_quota_day_used() >= FANIMETA_MAIL_DAY_MAX;
}

/**
 * 记一次发信。
 *
 * 注册验证码与密码重置邮件共享同一份额度 —— 否则攻击者挑其中一条通道猛打，
 * 照样能把邮件服务商的每日配额吃光，让另一条通道也失效。
 */
function fanimeta_mail_quota_day_add() {
	set_transient( fanimeta_mail_quota_day_key(), fanimeta_mail_quota_day_used() + 1, DAY_IN_SECONDS );
}

/**
 * 通用频次限制（按当前用户 + 动作名分桶）。
 *
 * 计数在动作执行「之前」+1：失败也计数，避免用无效请求反复试探。
 *
 * @param string $bucket 动作标识。
 * @param int    $max    窗口内允许的最大次数。
 * @param int    $window 窗口秒数。
 * @return true|WP_Error
 */
function fanimeta_rate_limit( $bucket, $max, $window ) {
	$key = 'fanimeta_rl_' . md5( $bucket . '|' . get_current_user_id() );
	$cnt = (int) get_transient( $key );

	if ( $cnt >= $max ) {
		return new WP_Error( 'fanimeta_rate_limited', '操作过于频繁，请稍后再试。' );
	}

	set_transient( $key, $cnt + 1, $window );

	return true;
}

/**
 * 取验证码相关的 transient key。
 *
 * @param string $email 邮箱。
 * @param string $kind  code / cool / try。
 * @return string
 */
function fanimeta_email_key( $email, $kind ) {
	return 'fanimeta_ec_' . $kind . '_' . md5( strtolower( trim( (string) $email ) ) );
}

/**
 * 生成并向指定邮箱发送注册验证码。
 *
 * @param string $email 目标邮箱。
 * @return true|WP_Error
 */
function fanimeta_email_send_code( $email ) {
	$email = sanitize_email( (string) $email );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'fanimeta_bad_email', '请输入正确的邮箱地址。' );
	}

	if ( ! fanimeta_smtp_configured() ) {
		return new WP_Error( 'fanimeta_smtp_missing', '站点尚未配置邮件发信服务，请联系管理员。' );
	}

	if ( get_transient( fanimeta_email_key( $email, 'cool' ) ) ) {
		return new WP_Error( 'fanimeta_too_fast', '发送太频繁了，请稍等一会儿再试。' );
	}

	/*
	 * 邮件轰炸防护（三层）。
	 * 上面那层冷却只按「收件邮箱」算，换个邮箱就绕过去了，而这个接口是匿名可达的
	 * （wp_ajax_nopriv_fanimeta_email_send_code）。攻击者据此可以把邮件服务商
	 * 每日 200 封的免费配额迅速打空 —— 后果不是"他被封号"，而是本站真实用户
	 * 收不到注册验证码，且发信域名信誉受损导致正常邮件进垃圾箱。
	 */
	$ip_key = 'fanimeta_ec_ip_' . md5( fanimeta_client_ip() );
	$ip_cnt = (int) get_transient( $ip_key );
	if ( $ip_cnt >= FANIMETA_EMAIL_IP_MAX_PER_HOUR ) {
		return new WP_Error( 'fanimeta_ip_limit', '操作过于频繁，请稍后再试。' );
	}

	if ( fanimeta_mail_quota_day_full() ) {
		return new WP_Error( 'fanimeta_day_limit', '今日发信量已达上限，请明日再试。' );
	}

	$code = (string) wp_rand( 100000, 999999 );

	set_transient( fanimeta_email_key( $email, 'code' ), $code, FANIMETA_EMAIL_CODE_TTL );
	set_transient( fanimeta_email_key( $email, 'cool' ), 1, FANIMETA_EMAIL_CODE_COOLDOWN );
	delete_transient( fanimeta_email_key( $email, 'try' ) );

	$sent = wp_mail(
		$email,
		sprintf( '【%s】注册邮箱验证码', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
		fanimeta_email_code_body( $code ),
		array( 'Content-Type: text/html; charset=UTF-8' )
	);

	if ( ! $sent ) {
		// 发送失败则退回验证码与冷却，允许用户立即重试。
		delete_transient( fanimeta_email_key( $email, 'code' ) );
		delete_transient( fanimeta_email_key( $email, 'cool' ) );
		return new WP_Error( 'fanimeta_mail_failed', '验证码发送失败，请稍后重试。' );
	}

	// 只有真的发出去了才占用配额 —— 失败请求不应该吃掉真实用户的额度。
	set_transient( $ip_key, $ip_cnt + 1, HOUR_IN_SECONDS );
	fanimeta_mail_quota_day_add();

	return true;
}

/**
 * 验证码邮件正文（HTML）。
 *
 * @param string $code 验证码。
 * @return string
 */
function fanimeta_email_code_body( $code ) {
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$site_url  = home_url( '/' );

	return '<div style="max-width:520px;margin:0 auto;padding:28px 24px;background:#faf6e8;border:2px solid #f7b500;border-radius:12px;font-family:-apple-system,\'Segoe UI\',\'Microsoft YaHei\',sans-serif;color:#5b5340;">'
		. '<h2 style="margin:0 0 12px;font-size:20px;color:#a67c00;">' . esc_html( $site_name ) . '</h2>'
		. '<p style="margin:0 0 18px;font-size:14px;line-height:1.7;">你好，你正在注册「' . esc_html( $site_name ) . '」账号，本次注册的邮箱验证码为：</p>'
		. '<p style="margin:0 0 18px;text-align:center;"><span style="display:inline-block;padding:12px 26px;background:#f7b500;color:#3d3320;font-size:30px;font-weight:700;letter-spacing:6px;border-radius:10px;">' . esc_html( $code ) . '</span></p>'
		. '<p style="margin:0 0 10px;font-size:13px;line-height:1.7;">验证码 <strong>5 分钟内</strong>有效，请勿转发给他人。若不是你本人操作，忽略本邮件即可。</p>'
		. '<p style="margin:0;padding-top:14px;border-top:1px dashed #e0d9c3;font-size:12px;color:#988f77;">本邮件由系统自动发送，请勿直接回复。<br>' . esc_html( $site_url ) . '</p>'
		. '</div>';
}

/**
 * 校验注册验证码是否正确（含错误次数限制）。
 *
 * @param string $email 邮箱。
 * @param string $input 用户填写的验证码。
 * @return true|WP_Error
 */
function fanimeta_email_check_code( $email, $input ) {
	$email  = sanitize_email( (string) $email );
	$input  = trim( (string) $input );
	$stored = get_transient( fanimeta_email_key( $email, 'code' ) );

	if ( '' === $input ) {
		return new WP_Error( 'fanimeta_code_empty', '请填写邮箱验证码。' );
	}

	if ( ! $stored ) {
		return new WP_Error( 'fanimeta_code_expired', '验证码不存在或已过期，请重新获取。' );
	}

	$tries = (int) get_transient( fanimeta_email_key( $email, 'try' ) );
	if ( $tries >= FANIMETA_EMAIL_CODE_MAX_TRY ) {
		delete_transient( fanimeta_email_key( $email, 'code' ) );
		return new WP_Error( 'fanimeta_code_locked', '错误次数过多，验证码已作废，请重新获取。' );
	}

	if ( ! hash_equals( (string) $stored, $input ) ) {
		set_transient( fanimeta_email_key( $email, 'try' ), $tries + 1, FANIMETA_EMAIL_CODE_TTL );
		return new WP_Error( 'fanimeta_code_wrong', '验证码不正确，请检查后重试。' );
	}

	return true;
}

/**
 * 清理某邮箱的验证码缓存（注册成功后调用，确保验证码一次性有效）。
 *
 * @param string $email 邮箱。
 */
function fanimeta_email_clear_code( $email ) {
	delete_transient( fanimeta_email_key( $email, 'code' ) );
	delete_transient( fanimeta_email_key( $email, 'try' ) );
	delete_transient( fanimeta_email_key( $email, 'cool' ) );
}

/**
 * AJAX：注册页点击「获取验证码」时发送邮件。
 */
function fanimeta_ajax_email_send_code() {
	check_ajax_referer( 'fanimeta_email_code', 'nonce' );

	$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$result = fanimeta_email_send_code( $email );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message'  => '验证码已发出，请到邮箱查收（5 分钟内有效）。',
			'cooldown' => FANIMETA_EMAIL_CODE_COOLDOWN,
		)
	);
}
add_action( 'wp_ajax_nopriv_fanimeta_email_send_code', 'fanimeta_ajax_email_send_code' );
add_action( 'wp_ajax_fanimeta_email_send_code', 'fanimeta_ajax_email_send_code' );

/**
 * 注册页表单尾部脚本：发送验证码、倒计时与状态提示。
 */
function fanimeta_register_footer_script() {
	if ( ! isset( $_GET['action'] ) || 'register' !== $_GET['action'] ) {
		return;
	}
	?>
	<script>
	(function () {
		var btn = document.getElementById('fanimeta-send-code');
		var tip = document.getElementById('fanimeta-code-tip');
		if (!btn || !tip) { return; }

		var textBtn = btn.textContent;
		var timer   = null;

		function say(msg, ok) {
			tip.textContent = msg;
			tip.className   = 'fanimeta-code-tip' + (ok ? ' is-ok' : ' is-error');
		}

		function countdown(seconds) {
			var left = seconds;
			btn.disabled = true;
			btn.classList.add('is-disabled');
			btn.textContent = left + ' 秒后可重发';
			timer = setInterval(function () {
				left--;
				if (left <= 0) {
					clearInterval(timer);
					btn.disabled = false;
					btn.classList.remove('is-disabled');
					btn.textContent = '\u91cd\u65b0\u83b7\u53d6';
					return;
				}
				btn.textContent = left + ' 秒后可重发';
			}, 1000);
		}

		btn.addEventListener('click', function () {
			var emailInput = document.getElementById('user_email');
			var email = emailInput ? emailInput.value.trim() : '';

			if (!email || email.indexOf('@') === -1) {
				say('请先填写正确的邮箱地址。', false);
				if (emailInput) { emailInput.focus(); }
				return;
			}

			btn.disabled = true;
			say('正在发送…', true);

			var body = new URLSearchParams();
			body.append('action', 'fanimeta_email_send_code');
			body.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'fanimeta_email_code' ) ); ?>);
			body.append('email', email);

			fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (res) { return res.json(); })
				.then(function (json) {
					if (json && json.success) {
						say(json.data.message, true);
						countdown(json.data.cooldown || 60);
					} else {
						say((json && json.data && json.data.message) || '发送失败，请稍后重试。', false);
						btn.disabled = false;
					}
				})
				.catch(function () {
					say('网络异常，请稍后重试。', false);
					btn.disabled = false;
				});
		});
	})();
	</script>
	<?php
}
add_action( 'login_footer', 'fanimeta_register_footer_script' );

/**
 * 后台「邮件服务」菜单：查看发信配置状态并发送测试邮件。
 */
function fanimeta_mail_menu() {
	add_menu_page(
		__( '邮件服务', 'fanimeta' ),
		__( '邮件服务', 'fanimeta' ),
		'manage_options',
		'fanimeta-mail',
		'fanimeta_mail_page_render',
		'dashicons-email-alt',
		72
	);
}
add_action( 'admin_menu', 'fanimeta_mail_menu' );

/**
 * 渲染后台「邮件服务」页面。
 */
function fanimeta_mail_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限访问', 'fanimeta' ) );
	}

	$fanimeta_configured = fanimeta_smtp_configured();
	$fanimeta_ok         = isset( $_GET['mail_ok'] ) ? sanitize_email( wp_unslash( $_GET['mail_ok'] ) ) : '';
	$fanimeta_err        = isset( $_GET['mail_err'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['mail_err'] ) ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '邮件服务', 'fanimeta' ); ?></h1>

		<?php if ( $fanimeta_ok ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo esc_html( '测试邮件已发送至 ' . $fanimeta_ok . '，请到收件箱（含垃圾箱）确认。' ); ?>
			</p></div>
		<?php endif; ?>

		<?php if ( $fanimeta_err ) : ?>
			<div class="notice notice-error is-dismissible"><p>
				<?php echo esc_html( '发送失败：' . $fanimeta_err ); ?>
			</p></div>
		<?php endif; ?>

		<h2>发信通道状态</h2>
		<table class="widefat striped" style="max-width:760px;">
			<tbody>
				<tr>
					<td style="width:170px;"><strong>状态</strong></td>
					<td>
						<?php if ( $fanimeta_configured ) : ?>
							<span style="color:#1a7f37;font-weight:600;">已配置，邮件通过 SMTP 发送</span>
						<?php else : ?>
							<span style="color:#b32d2e;font-weight:600;">未配置</span>
							－ 需在 wp-config.php 中填写 FANIMETA_SMTP_USER 与 FANIMETA_SMTP_PASS
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $fanimeta_configured ) : ?>
					<tr>
						<td><strong>SMTP 服务器</strong></td>
						<td><?php echo esc_html( ( defined( 'FANIMETA_SMTP_HOST' ) ? FANIMETA_SMTP_HOST : 'smtp.qq.com' ) . ':' . ( defined( 'FANIMETA_SMTP_PORT' ) ? FANIMETA_SMTP_PORT : 465 ) ); ?></td>
					</tr>
					<tr>
						<td><strong>加密方式</strong></td>
						<td><?php echo esc_html( strtoupper( defined( 'FANIMETA_SMTP_SECURE' ) ? FANIMETA_SMTP_SECURE : 'ssl' ) ); ?></td>
					</tr>
					<tr>
						<td><strong>发信邮箱</strong></td>
						<td><?php echo esc_html( FANIMETA_SMTP_USER ); ?></td>
					</tr>
					<tr>
						<td><strong>发件人名称</strong></td>
						<td><?php echo esc_html( defined( 'FANIMETA_SMTP_FROM_NAME' ) ? FANIMETA_SMTP_FROM_NAME : get_bloginfo( 'name' ) ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<h2>发送测试邮件</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fanimeta_send_test_mail">
			<?php wp_nonce_field( 'fanimeta_send_test_mail' ); ?>
			<p>
				<label for="fanimeta_test_email">收件邮箱：</label>
				<input type="email" id="fanimeta_test_email" name="fanimeta_test_email" class="regular-text" required
					value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
			</p>
			<p class="description">点击后会立即通过当前配置的 SMTP 发出一封测试邮件，可用于确认账号与授权码是否正确。</p>
			<p><button type="submit" class="button button-primary">发送测试邮件</button></p>
		</form>

		<h2>配置说明</h2>
		<p class="description">在站点根目录的 <code>wp-config.php</code> 中，于「That's all, stop editing!」之前加入以下常量即可（QQ 邮箱示例）：</p>
		<pre style="max-width:760px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:12px;">define( 'FANIMETA_SMTP_HOST', 'smtp.qq.com' );
define( 'FANIMETA_SMTP_PORT', 465 );
define( 'FANIMETA_SMTP_SECURE', 'ssl' );
define( 'FANIMETA_SMTP_USER', '你的QQ号@qq.com' );
define( 'FANIMETA_SMTP_PASS', '邮箱SMTP授权码' );
define( 'FANIMETA_SMTP_FROM', '你的QQ号@qq.com' );
define( 'FANIMETA_SMTP_FROM_NAME', '你的站点名' );</pre>
	</div>
	<?php
}

/**
 * 处理后台测试发信请求。
 */
function fanimeta_send_test_mail_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限操作', 'fanimeta' ) );
	}
	check_admin_referer( 'fanimeta_send_test_mail' );

	$fanimeta_to = isset( $_POST['fanimeta_test_email'] ) ? sanitize_email( wp_unslash( $_POST['fanimeta_test_email'] ) ) : '';
	$fanimeta_url = admin_url( 'admin.php?page=fanimeta-mail' );

	if ( ! is_email( $fanimeta_to ) ) {
		wp_safe_redirect( add_query_arg( 'mail_err', rawurlencode( '收件邮箱格式不正确。' ), $fanimeta_url ) );
		exit;
	}

	if ( ! fanimeta_smtp_configured() ) {
		wp_safe_redirect( add_query_arg( 'mail_err', rawurlencode( '尚未在 wp-config.php 中配置 SMTP 账号。' ), $fanimeta_url ) );
		exit;
	}

	$fanimeta_sent = wp_mail(
		$fanimeta_to,
		sprintf( '【%s】邮件服务测试', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
		fanimeta_email_code_body( '123456' ),
		array( 'Content-Type: text/html; charset=UTF-8' )
	);

	if ( ! $fanimeta_sent ) {
		wp_safe_redirect( add_query_arg( 'mail_err', rawurlencode( 'SMTP 投递失败，请检查服务器与授权码。' ), $fanimeta_url ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'mail_ok', rawurlencode( $fanimeta_to ), $fanimeta_url ) );
	exit;
}
add_action( 'admin_post_fanimeta_send_test_mail', 'fanimeta_send_test_mail_handler' );

/**
 * 主题激活时自动创建「关于」「投稿」页面（若不存在）
 */
function fanimeta_create_default_pages() {
	if ( ! get_page_by_path( 'about' ) ) {
		wp_insert_post(
			array(
				'post_title'   => __( '关于', 'fanimeta' ),
				'post_name'    => 'about',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => __( '你好，欢迎来到本站。这里是一个关于动画与二次元的个人博客，会不定期更新新番前瞻、番剧评论、动画音乐推荐等内容。', 'fanimeta' ),
			)
		);
	}

	if ( ! get_page_by_path( 'submit' ) ) {
		$submit_id = wp_insert_post(
			array(
				'post_title'  => __( '投稿', 'fanimeta' ),
				'post_name'   => 'submit',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		if ( $submit_id && ! is_wp_error( $submit_id ) ) {
			update_post_meta( $submit_id, '_wp_page_template', 'page-submit.php' );
		}
	}

	if ( ! get_page_by_path( 'profile' ) ) {
		$profile_id = wp_insert_post(
			array(
				'post_title'  => __( '个人主页', 'fanimeta' ),
				'post_name'   => 'profile',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		if ( $profile_id && ! is_wp_error( $profile_id ) ) {
			update_post_meta( $profile_id, '_wp_page_template', 'page-profile.php' );
		}
	}
}
add_action( 'after_switch_theme', 'fanimeta_create_default_pages' );

/**
 * 获取「投稿」页链接（供前端投稿入口使用）
 */
function fanimeta_get_submit_page_url() {
	$page = get_page_by_path( 'submit' );
	return $page ? get_permalink( $page ) : home_url( '/submit/' );
}

/**
 * 获取「个人主页」页链接
 */
function fanimeta_get_profile_page_url() {
	$page = get_page_by_path( 'profile' );
	return $page ? get_permalink( $page ) : home_url( '/profile/' );
}

/**
 * 生成某个用户的个人主页链接（带 uid 参数）。
 */
function fanimeta_profile_url( $user_id = 0 ) {
	$fanimeta_base = fanimeta_get_profile_page_url();
	$fanimeta_sep  = ( false === strpos( $fanimeta_base, '?' ) ) ? '?' : '&';
	return $fanimeta_base . $fanimeta_sep . 'uid=' . (int) $user_id;
}

/**
 * 昵称是否已被他人占用。
 *
 * 同时检查 display_name / user_nicename / user_login：
 * 后两者同样参与"这个人看起来像谁"的判断，只查 display_name 会留下绕道空间。
 *
 * @param string $nickname   待检查的昵称。
 * @param int    $exclude_id 排除的用户 ID（通常是当前用户自己）。
 * @return bool
 */
function fanimeta_nickname_taken( $nickname, $exclude_id = 0 ) {
	global $wpdb;

	$nickname = trim( (string) $nickname );
	if ( '' === $nickname ) {
		return false;
	}

	$fanimeta_found = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} WHERE ( display_name = %s OR user_nicename = %s OR user_login = %s ) AND ID <> %d LIMIT 1",
			$nickname,
			$nickname,
			$nickname,
			(int) $exclude_id
		)
	);

	return ! empty( $fanimeta_found );
}

/**
 * 解析「uid」类查询关键词，支持 uid1、uid:1、UID: 1 等格式。
 *
 * @param string $query 搜索关键词。
 * @return int|false 解析出的用户 ID，未匹配则返回 false。
 */
function fanimeta_parse_uid_query( $query ) {
	if ( preg_match( '/^uid\s*:?\s*(\d+)$/i', trim( (string) $query ), $fanimeta_m ) ) {
		return (int) $fanimeta_m[1];
	}
	return false;
}

/**
 * 按 UID 或昵称（登录名 / 显示名 / nickname）查找用户。
 * 支持 uid1 / uid:1 / 纯数字按 UID 精确匹配，否则按登录名、slug、显示名、nickname 依次匹配。
 */
function fanimeta_find_user( $query ) {
	$fanimeta_query = trim( (string) $query );
	if ( '' === $fanimeta_query ) {
		return false;
	}

	// uid1 / uid:1 / UID: 1 等格式 → 按 UID
	$fanimeta_uid = fanimeta_parse_uid_query( $fanimeta_query );
	if ( $fanimeta_uid ) {
		$fanimeta_user = get_user_by( 'id', $fanimeta_uid );
		if ( $fanimeta_user ) {
			return $fanimeta_user;
		}
	}

	// 纯数字 → 按 UID
	if ( ctype_digit( $fanimeta_query ) ) {
		$fanimeta_user = get_user_by( 'id', (int) $fanimeta_query );
		if ( $fanimeta_user ) {
			return $fanimeta_user;
		}
	}

	// 登录名精确
	$fanimeta_user = get_user_by( 'login', $fanimeta_query );
	if ( $fanimeta_user ) {
		return $fanimeta_user;
	}

	// slug（nicename）精确
	$fanimeta_user = get_user_by( 'slug', sanitize_title( $fanimeta_query ) );
	if ( $fanimeta_user ) {
		return $fanimeta_user;
	}

	// 显示名 / nicename / 登录名 模糊搜索。
	// 刻意用 search_columns 排除 user_email —— 默认搜索列是含邮箱的，
	// 输入邮箱片段就能反查到账号，那就成了注册页防枚举之外的另一条枚举通道。
	$fanimeta_users = get_users(
		array(
			'search'         => '*' . $fanimeta_query . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
			'number'         => 1,
		)
	);
	if ( ! empty( $fanimeta_users ) ) {
		return $fanimeta_users[0];
	}

	// nickname meta 精确
	$fanimeta_users = get_users(
		array(
			'meta_key'   => 'nickname',
			'meta_value' => $fanimeta_query,
			'number'     => 1,
		)
	);
	if ( ! empty( $fanimeta_users ) ) {
		return $fanimeta_users[0];
	}

	return false;
}

/**
 * 搜索栏输入 uid1 / uid:1 等关键词时，直接跳转到该用户的个人主页。
 */
function fanimeta_search_uid_redirect() {
	if ( ! is_search() ) {
		return;
	}

	$fanimeta_uid = fanimeta_parse_uid_query( get_search_query() );
	if ( $fanimeta_uid && get_user_by( 'id', $fanimeta_uid ) ) {
		wp_safe_redirect( fanimeta_profile_url( $fanimeta_uid ) );
		exit;
	}
}
add_action( 'template_redirect', 'fanimeta_search_uid_redirect' );

/**
 * 判断 ?author= 的值是否「能被解释成数字」。
 *
 * 上一版只认 ctype_digit()（纯 0-9 字符串），而它对其余写法一律返回 false
 * 直接放行 —— "1,2" / "+1" / "1 " / "0x1" 四种写法都能绕过拦截
 * （2026-10-09 扫描确认，四种写法均返回 200 且 body_class 吐出 author-<slug>）。
 * 这里先归一化（数组 / 逗号 / 空白拆分、去前导正号），再逐个判断。
 *
 * @param mixed $raw 原始 $_GET['author']（可能为数组）。
 * @return bool
 */
function fanimeta_is_numeric_author_query( $raw ) {
	// ?author[]=1 / ?author[0]=1 —— 一律按枚举处理。
	if ( is_array( $raw ) ) {
		return true;
	}

	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return false;
	}

	// 逗号与空白拆开逐段判断（?author=1,2,3）。
	$parts = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
	foreach ( $parts as $part ) {
		// 去掉前导正号后是纯数字（+1 / 1）。
		if ( preg_match( '/^\d+$/', ltrim( $part, '+' ) ) ) {
			return true;
		}
		// 十六进制形式（0x1）。
		if ( preg_match( '/^0x[0-9a-f]+$/i', $part ) ) {
			return true;
		}
	}

	return false;
}

/**
 * 关闭作者归档，并拦截 ?author=N 形式的数字枚举。
 *
 * 这里有两条彼此独立的通道，必须一起堵：
 *
 *   1. WordPress 会把 /?author=1 301 到 /author/<user_nicename>/，
 *      而 user_nicename 默认就是登录名 slug 化后的结果 —— 攻击者拿一个
 *      递增整数就能把站点的登录名（撞库攻击的一半凭据）整站遍历出来。
 *
 *   2. WordPress 自动生成的作者 sitemap（/wp-sitemap-users-1.xml）
 *      会主动把所有作者归档 URL 公布出来，等于前门锁了、后窗开着。
 *
 * 站内的作者链接走自建个人主页（fanimeta_profile_url，/?uid=N），
 * 导航与文章页都不依赖 /author/<slug>/ —— 因此直接把作者归档整站 404，
 * 从根上消除这条通道；sitemap 由 fanimeta_disable_users_sitemap() 撤下。
 *
 * 优先级 1：必须早于 redirect_canonical（优先级 10）执行，否则 301 已经发出去了。
 */
function fanimeta_block_author_enum() {
	if ( is_admin() ) {
		return;
	}

	// 1) ?author=<能被解释成数字> —— 数字枚举没有存在必要。
	if ( isset( $_GET['author'] ) && fanimeta_is_numeric_author_query( wp_unslash( $_GET['author'] ) ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}

	// 2) 作者归档整站 404（含 /author/<slug>/ 及其 feed）。
	if ( is_author() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'fanimeta_block_author_enum', 1 );

/**
 * 撤下 WordPress 自动生成的「作者 sitemap」。
 *
 * /wp-sitemap-users-1.xml 会列出所有有已发布文章的作者归档 URL
 * （/author/<user_nicename>/）。作者归档已整站 404，
 * 这里顺带把公布名单也一起撤掉，避免搜索引擎索引到登录名 slug。
 */
function fanimeta_disable_users_sitemap( $provider, $name ) {
	if ( 'users' === $name ) {
		return false;
	}
	return $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'fanimeta_disable_users_sitemap', 10, 2 );

/**
 * 统一 wp-login.php 的登录失败文案。
 *
 * WordPress 默认会区分「用户名未注册」与「密码不正确」两种提示，
 * 攻击者据此可以逐个确认账号是否存在（2026-10-09 扫描确认）。
 * 自建 /login/ 已做中性处理（见 inc/auth.php），这一层把 wp-login.php 补齐 ——
 * 后台入口仍保留 action=login 供管理员使用，所以必须在这里收敛信息，
 * 否则前面的收口效果会被这一条差异抹平。
 */
function fanimeta_uniform_login_error() {
	return __( '账号或密码不正确，请重新输入。', 'fanimeta' );
}
add_filter( 'login_errors', 'fanimeta_uniform_login_error' );

/**
 * 去掉静态资源 URL 上的 WordPress 版本号（?ver=7.1.3）。
 *
 * 版本号会让扫描器一眼看出「这个站值不值得试」。主题自身的资源改用主题
 * 版本号（浏览器缓存仍能正确失效），核心资源则只摘掉参数。
 */
function fanimeta_filter_asset_src( $src ) {
	if ( false === strpos( $src, 'ver=' ) ) {
		return $src;
	}

	if ( false !== strpos( $src, '/wp-content/themes/fanimeta/' ) ) {
		return add_query_arg( 'ver', FANIMETA_VERSION, remove_query_arg( 'ver', $src ) );
	}

	return remove_query_arg( 'ver', $src );
}
add_filter( 'script_loader_src', 'fanimeta_filter_asset_src', 15 );
add_filter( 'style_loader_src', 'fanimeta_filter_asset_src', 15 );

/**
 * 未登录访客不得列举媒体库。
 *
 * /wp-json/wp/v2/media?per_page=100 会把全部附件的 ID、上传者 UID、
 * 文件名与完整 URL 一次吐出（2026-10-09 扫描确认，单次响应 15KB）。
 * 本站零插件、前台不消费 REST，因此对未登录访客直接关闭媒体端点；
 * 后台区块编辑器仍可正常使用（管理员已登录）。
 */
function fanimeta_restrict_rest_endpoints( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}

	foreach ( array( '/wp/v2/media', '/wp/v2/media/(?P<id>[\d]+)' ) as $route ) {
		unset( $endpoints[ $route ] );
	}

	return $endpoints;
}
add_filter( 'rest_endpoints', 'fanimeta_restrict_rest_endpoints' );

/**
 * 在 robots.txt 中声明不要抓取作者归档与 author 查询。
 *
 * 作者归档本身已整站 404（见 fanimeta_block_author_enum），这条声明的作用
 * 是减少爬虫的无谓请求，并避免登录名 slug 出现在搜索引擎结果里。
 *
 * 注意：2026-10-09 之前 Nginx 里有一条裸的 `location = /robots.txt`，
 * 它把请求短路在 Nginx 层，WordPress 的虚拟 robots.txt 根本到不了 ——
 * 该问题已在本轮 Nginx 加固中修复（删除该 location）。
 */
function fanimeta_robots_txt( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}

	$output .= "\nDisallow: /author/\n";
	$output .= "Disallow: /?author=\n";

	return $output;
}
add_filter( 'robots_txt', 'fanimeta_robots_txt', 10, 2 );


/**
 * 主题激活时开启用户注册。
 *
 * 刻意不再写 update_option( 'default_role', ... )：
 * 激活一个主题不该顺手改掉「新用户角色」这种安全相关的设置 ——
 * 否则管理员手动降级成订阅者之后，下次换主题又会被静默改回作者。
 * 存量站点的角色迁移见 fanimeta_maybe_downgrade_default_role()。
 */
function fanimeta_enable_registration() {
	update_option( 'users_can_register', 1 );
}
add_action( 'after_switch_theme', 'fanimeta_enable_registration' );

/**
 * 一次性把「新用户默认角色」从 author 降级为 contributor。
 *
 * 为什么必须降：author 拥有 publish_posts 与 upload_files ——
 * 结合 page-submit.php 里 `current_user_can('publish_posts') ? 'publish' : 'pending'`，
 * 新注册用户投的稿会直接上线、跳过审核；同时还能无限上传文件，容易被当成免费图床。
 * contributor 只能提交待审稿件，对社区站是更合适的默认值。
 *
 * 用 option 打标记，只迁移一次，之后完全尊重管理员在后台的选择。
 */
function fanimeta_maybe_downgrade_default_role() {
	if ( 'contributor' === get_option( 'fanimeta_default_role_migrated' ) ) {
		return;
	}

	if ( 'author' === get_option( 'default_role' ) ) {
		update_option( 'default_role', 'contributor' );
	}

	update_option( 'fanimeta_default_role_migrated', 'contributor', false );
}
add_action( 'init', 'fanimeta_maybe_downgrade_default_role' );

/**
 * 注册后自动登录（本地 phpStudy 通常未配置 SMTP、发不出密码邮件，此钩子让注册流程可直接跑通）。
 *
 * 必须判断提交来源：user_register 是「数据创建」钩子，不是「注册流程」钩子，
 * 它在所有建号路径上都会触发。少了这个判断会出现两类事故：
 *   1. 管理员在后台「用户 → 添加用户」建号时，管理员自己的会话被切换成刚创建的用户；
 *   2. 任何插件调用 wp_insert_user() / wp_create_user()（用户批量导入、商城下单建号……）
 *      都会让当前请求者直接登入刚创建的账号。
 */
function fanimeta_auto_login_after_register( $user_id ) {
	$action = isset( $_POST['fanimeta_auth_action'] ) ? sanitize_key( wp_unslash( $_POST['fanimeta_auth_action'] ) ) : '';

	if ( 'register' !== $action ) {
		return;
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
}
add_action( 'user_register', 'fanimeta_auto_login_after_register' );

/**
 * 注册成功后直接跳转首页（配合自动登录，避免停在“检查邮箱”页面）。
 */
function fanimeta_registration_redirect( $redirect_to ) {
	return home_url( '/' );
}
add_filter( 'registration_redirect', 'fanimeta_registration_redirect' );

/**
 * 默认导航菜单（未在后台设置菜单时使用）
 * 依次显示：首页(Home)、关于(About)、其余已发布页面
 * 「投稿」页已从菜单移除（由右上角「投稿」按钮承担，避免重复）。
 */
function fanimeta_default_menu() {
	$about_page  = get_page_by_path( 'about' );
	$submit_page = get_page_by_path( 'submit' );
	$menu        = '<ul class="menu">';

	// 首页
	$menu .= '<li class="menu-item' . ( is_front_page() && is_home() ? ' current-menu-item' : '' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( '首页', 'fanimeta' ) . '</a></li>';

	// 关于
	if ( $about_page ) {
		$menu .= '<li class="menu-item"><a href="' . esc_url( get_permalink( $about_page ) ) . '">' . esc_html__( '关于', 'fanimeta' ) . '</a></li>';
	} else {
		$menu .= '<li class="menu-item"><a href="' . esc_url( home_url( '/about/' ) ) . '">' . esc_html__( '关于', 'fanimeta' ) . '</a></li>';
	}

	// 其余页面（排除：关于、投稿）
	$exclude = array();
	if ( $about_page ) {
		$exclude[] = $about_page->ID;
	}
	if ( $submit_page ) {
		$exclude[] = $submit_page->ID;
	}

	$pages = get_pages(
		array(
			'sort_column' => 'menu_order,post_title',
			'exclude'     => $exclude,
		)
	);
	foreach ( $pages as $page ) {
		$menu .= '<li class="menu-item"><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $page->post_title ) . '</a></li>';
	}

	$menu .= '</ul>';

	echo $menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 已在拼接时逐项转义
}

/**
 * 隐藏后台：非管理员访问 /wp-admin/ 时重定向回首页
 * 普通登录用户（作者等）看不到后台入口，后台仅管理员可用。
 */
function fanimeta_hide_admin() {
	// 放行后台 AJAX 请求，避免影响编辑器等异步操作
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	// 非管理员一律拦截回首页
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}
add_action( 'admin_init', 'fanimeta_hide_admin' );

/**
 * 隐藏前台顶部的 WordPress 管理工具栏（admin bar）。
 * 登录用户访问前台页面时，顶部不再显示带 "WordPress" 字样的黑色长条；
 * 后台（wp-admin）内的工具栏保留，方便管理员操作。
 */
function fanimeta_hide_admin_bar( $show ) {
	// 仅前台隐藏，后台保留
	if ( ! is_admin() ) {
		return false;
	}
	return $show;
}
add_filter( 'show_admin_bar', 'fanimeta_hide_admin_bar' );

/**
 * 允许中文用户名（用户名可包含中文、字母、数字、下划线）。
 * 否则 wp_update_user / wp_insert_user 会对中文用户名做 sanitize 时过滤成空，导致更新失败。
 *
 * 注意：邮箱地址（含 @）必须原样保留，不做字符过滤——
 * 因为 wp_authenticate() 会先 sanitize_user(登录输入)，若把邮箱里的 @/. 删掉，
 * wp_authenticate_email_password() 里的 is_email() 会判定失败，导致邮箱无法登录。
 */
function fanimeta_sanitize_user( $username, $raw_username, $strict ) {
	if ( false !== strpos( (string) $raw_username, '@' ) ) {
		return $username;
	}
	$username = wp_strip_all_tags( $raw_username );
	$username = remove_accents( $username );
	// 允许：中文、字母、数字、下划线、空格
	$username = preg_replace( '|[^a-zA-Z0-9 _\x{4e00}-\x{9fa5}]|u', '', $username );
	return trim( $username );
}
add_filter( 'sanitize_user', 'fanimeta_sanitize_user', 10, 3 );

/**
 * 修复中文用户名 user_nicename 过长导致注册失败的问题。
 *
 * wp_insert_user() 会把 user_nicename 交给 sanitize_title()，而 sanitize_title()
 * 会把非 ASCII 字符（中文）转成 URL 编码（每个汉字变 9 字符，如「栗」→ %e6%a0%97），
 * 随后用 mb_strlen() 检查不超过 50 字符，超限即返回 user_nicename_too_long 错误。
 * 因此中文昵称只要超过约 5 个汉字就会注册失败。
 *
 * 这里在 pre_user_nicename 阶段把超长结果安全截断到 50 字符以内（保证落在 %xx 编码边界），
 * 避免破坏 URL 编码；截断后若重名，WordPress 自会追加 -2 后缀处理冲突。
 */
function fanimeta_fix_user_nicename( $user_nicename ) {
	if ( mb_strlen( (string) $user_nicename ) <= 50 ) {
		return $user_nicename;
	}

	$user_nicename = mb_substr( (string) $user_nicename, 0, 50 );

	// 若截断点落在某个 %xx 编码序列中间，则退回到上一个完整序列的末尾。
	$fanimeta_last_pct = strrpos( $user_nicename, '%' );
	if ( false !== $fanimeta_last_pct ) {
		$fanimeta_tail = substr( $user_nicename, $fanimeta_last_pct );
		if ( strlen( $fanimeta_tail ) < 3 ) {
			$user_nicename = substr( $user_nicename, 0, $fanimeta_last_pct );
		}
	}

	return rtrim( $user_nicename, '-_' );
}
add_filter( 'pre_user_nicename', 'fanimeta_fix_user_nicename' );

/**
 * 用户自定义头像：优先使用用户上传的头像（user meta: fanimeta_avatar），
 * 否则回退到 WordPress 默认的 Gravatar 头像。
 */
function fanimeta_custom_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
	$user = false;

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', (int) $id_or_email );
	} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
		$user = get_user_by( 'id', (int) $id_or_email->user_id );
	} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
	}

	if ( ! $user ) {
		return $avatar;
	}

	$avatar_id = get_user_meta( $user->ID, 'fanimeta_avatar', true );
	if ( ! $avatar_id ) {
		return $avatar;
	}

	$avatar_url = wp_get_attachment_image_url( (int) $avatar_id, 'thumbnail' );
	if ( ! $avatar_url ) {
		return $avatar;
	}

	$avatar = sprintf(
		'<img alt="%1$s" src="%2$s" class="avatar avatar-%3$d photo" width="%3$d" height="%3$d" />',
		esc_attr( $alt ),
		esc_url( $avatar_url ),
		(int) $size
	);

	return $avatar;
}
add_filter( 'get_avatar', 'fanimeta_custom_avatar', 10, 5 );

/**
 * 评论头像右下角认证角标 + 点击进入评论者个人主页。
 * 通过 get_avatar 过滤器识别评论场景（WP_Comment 对象）：
 * - 注册用户的评论头像包进链接，点击直达其个人主页；
 * - 站长/认证用户额外叠加认证角标；
 * - 游客评论保持原样。
 */
function fanimeta_comment_avatar_verify( $avatar, $id_or_email, $size, $default, $alt ) {
	if ( ! is_object( $id_or_email ) || ! isset( $id_or_email->comment_ID ) ) {
		return $avatar;
	}

	$user_id = isset( $id_or_email->user_id ) ? (int) $id_or_email->user_id : 0;
	if ( ! $user_id ) {
		return $avatar;
	}

	$badge = fanimeta_avatar_verify_html( $user_id );
	$inner = '<span class="comment-avatar-wrap">' . $avatar . $badge . '</span>';

	return '<a class="comment-avatar-link" href="' . esc_url( fanimeta_profile_url( $user_id ) ) . '" title="' . esc_attr__( '查看个人主页', 'fanimeta' ) . '">' . $inner . '</a>';
}
add_filter( 'get_avatar', 'fanimeta_comment_avatar_verify', 20, 5 );

/**
 * 评论者昵称链接到其个人主页（仅注册用户；游客保持原样）。
 * 注意：get_comment_author_link 过滤器第三个参数是 $comment_id（评论ID），
 * 必须先取回评论对象再拿 user_id，不能直接当用户ID用。
 */
function fanimeta_comment_author_profile_link( $return, $author, $comment_id ) {
	$fanimeta_comment = get_comment( $comment_id );
	if ( ! $fanimeta_comment ) {
		return $return;
	}

	$fanimeta_user_id = (int) $fanimeta_comment->user_id;
	if ( ! $fanimeta_user_id ) {
		return $return;
	}

	$fanimeta_user = get_userdata( $fanimeta_user_id );
	if ( ! $fanimeta_user ) {
		return $return;
	}

	return '<a class="comment-author-link" href="' . esc_url( fanimeta_profile_url( $fanimeta_user_id ) ) . '" rel="author">' . esc_html( $fanimeta_user->display_name ) . '</a>';
}
add_filter( 'get_comment_author_link', 'fanimeta_comment_author_profile_link', 10, 3 );

/**
 * 站长认证：UID 1（首位管理员）与 UID 2（正式站长）为站长账号。
 * 此处不写具体登录名，避免源码公开后暴露后台账号线索。
 */
function fanimeta_is_owner( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$fanimeta_owner_ids = array( 1, 2 );
	return in_array( (int) $user_id, $fanimeta_owner_ids, true );
}

/**
 * 是否带认证角标：站长（UID 1/2）或后台管理员手动认证的用户（user meta: fanimeta_verified）。
 */
function fanimeta_user_has_verify( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$user_id = (int) $user_id;
	return fanimeta_is_owner( $user_id ) || (bool) get_user_meta( $user_id, 'fanimeta_verified', true );
}

/**
 * 头像右下角认证角标：站长为橙色C标，管理员认证的用户为绿色C标。
 * 叠放在头像容器内，供文章作者、搜索用户、个人主页、顶栏头像、评论区等场景使用。
 */
function fanimeta_avatar_verify_html( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$user_id = (int) $user_id;

	if ( fanimeta_is_owner( $user_id ) ) {
		$fanimeta_src   = 'verify-badge.png';
		$fanimeta_label = __( '站长', 'fanimeta' );
	} elseif ( get_user_meta( $user_id, 'fanimeta_verified', true ) ) {
		$fanimeta_src   = 'verify-badge-green.png';
		$fanimeta_label = __( '认证用户', 'fanimeta' );
	} else {
		return '';
	}

	return '<img class="avatar-verify" src="' . esc_url( get_template_directory_uri() . '/assets/images/' . $fanimeta_src ) . '" alt="' . esc_attr( $fanimeta_label ) . '" title="' . esc_attr( $fanimeta_label ) . '">';
}

/**
 * 输出「站长」认证徽章（昵称旁的黄色文字胶囊）。
 * 认证图标已改为叠在头像右下角，徽章只保留文字，避免重复。
 */
function fanimeta_owner_badge( $user_id = 0 ) {
	if ( ! fanimeta_is_owner( $user_id ) ) {
		return;
	}
	printf(
		'<span class="owner-badge" title="%1$s"><span class="owner-badge-text">%1$s</span></span>',
		esc_attr__( '站长', 'fanimeta' )
	);
}

/**
 * 输出文章作者徽章：头像（右下角认证角标）+ 昵称（链接到作者主页）。
 * 用于文章列表与详情页的 meta 区。
 */
function fanimeta_post_author_badge() {
	$fanimeta_author_id   = get_the_author_meta( 'ID' );
	$fanimeta_author_name = get_the_author_meta( 'display_name' );
	$fanimeta_author_url  = fanimeta_profile_url( $fanimeta_author_id );

	printf(
		'<span class="post-author" itemprop="author" itemscope itemtype="https://schema.org/Person"><a class="post-author-link" href="%1$s" rel="author" itemprop="url"><span class="author-avatar-wrap">%2$s%3$s</span><span class="post-author-name" itemprop="name">%4$s</span></a></span>',
		esc_url( $fanimeta_author_url ),
		get_avatar( $fanimeta_author_id, 22, '', esc_attr( $fanimeta_author_name ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		fanimeta_avatar_verify_html( $fanimeta_author_id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html( $fanimeta_author_name )
	);
}

/**
 * 备案信息自定义设置（Customizer）。
 * 用户可在后台「外观 → 自定义 → 备案信息」填写 ICP 备案号与公安备案号，
 * 填好后自动显示在页脚。
 */
function fanimeta_customize_register( $wp_customize ) {
	// 开屏公告设置
	$wp_customize->add_section(
		'fanimeta_notice',
		array(
			'title'       => __( '开屏公告', 'fanimeta' ),
			'priority'    => 120,
			'description' => __( '设置每次进入网站时展示的开屏公告弹窗，用户确认后关闭。', 'fanimeta' ),
		)
	);

	$wp_customize->add_setting(
		'fanimeta_notice_text',
		array(
			'default'           => '本网站属于同人二创网站，如有侵权此网站会立刻关停',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'fanimeta_notice_text',
		array(
			'label'       => __( '公告内容', 'fanimeta' ),
			'description' => __( '开屏弹窗展示的公告文字，留空则不再显示弹窗。', 'fanimeta' ),
			'section'     => 'fanimeta_notice',
			'type'        => 'textarea',
		)
	);

	// 页脚设置
	$wp_customize->add_section(
		'fanimeta_footer',
		array(
			'title'       => __( '页脚设置', 'fanimeta' ),
			'priority'    => 125,
			'description' => __( '自定义网站页脚的版权文字、附加内容与驱动信息显示。', 'fanimeta' ),
		)
	);

	// 版权行
	$wp_customize->add_setting(
		'fanimeta_copyright',
		array(
			'default'           => '&copy; {year} <a href="{site_url}">{site_name}</a> · 版权所有',
			'sanitize_callback' => 'wp_kses_post',
		)
	);
	$wp_customize->add_control(
		'fanimeta_copyright',
		array(
			'label'       => __( '版权行', 'fanimeta' ),
			'description' => __( '页脚版权行内容，支持 HTML。可用占位符：{year}=年份、{site_name}=站点名、{site_url}=首页链接。', 'fanimeta' ),
			'section'     => 'fanimeta_footer',
			'type'        => 'textarea',
		)
	);

	// 页脚自定义内容
	$wp_customize->add_setting(
		'fanimeta_footer_text',
		array(
			'default'           => '',
			'sanitize_callback' => 'wp_kses_post',
		)
	);
	$wp_customize->add_control(
		'fanimeta_footer_text',
		array(
			'label'       => __( '页脚自定义内容', 'fanimeta' ),
			'description' => __( '显示在版权信息下方的自定义内容，支持基本 HTML（如链接、加粗）。留空则不显示。', 'fanimeta' ),
			'section'     => 'fanimeta_footer',
			'type'        => 'textarea',
		)
	);

	// 驱动信息行
	$wp_customize->add_setting(
		'fanimeta_powered_by',
		array(
			'default'           => '由 <a href="https://wordpress.org/" target="_blank" rel="noopener">WordPress</a> &amp; <a href="{site_url}">Fanimeta</a> 强力驱动',
			'sanitize_callback' => 'wp_kses_post',
		)
	);
	$wp_customize->add_control(
		'fanimeta_powered_by',
		array(
			'label'       => __( '驱动信息', 'fanimeta' ),
			'description' => __( '页脚「强力驱动」行内容，支持 HTML 与 {site_url} 占位符，留空则不显示。', 'fanimeta' ),
			'section'     => 'fanimeta_footer',
			'type'        => 'textarea',
		)
	);

	$wp_customize->add_section(
		'fanimeta_beian',
		array(
			'title'       => __( '备案信息', 'fanimeta' ),
			'priority'    => 130,
			'description' => __( '填写网站 ICP 备案号与公安备案号，将显示在页脚。', 'fanimeta' ),
		)
	);

	// ICP 备案号
	$wp_customize->add_setting(
		'fanimeta_icp',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'fanimeta_icp',
		array(
			'label'       => __( 'ICP 备案号', 'fanimeta' ),
			'description' => __( '例如：京ICP备00000000号', 'fanimeta' ),
			'section'     => 'fanimeta_beian',
			'type'        => 'text',
		)
	);

	// 公安备案号
	$wp_customize->add_setting(
		'fanimeta_public_security',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'fanimeta_public_security',
		array(
			'label'       => __( '公安备案号', 'fanimeta' ),
			'description' => __( '例如：京公网安备 00000000000000号', 'fanimeta' ),
			'section'     => 'fanimeta_beian',
			'type'        => 'text',
		)
	);
}
add_action( 'customize_register', 'fanimeta_customize_register' );

/**
 * 页脚占位符替换。
 * 支持 {year}、{site_name}、{site_url} 三个占位符。
 *
 * @param string $text 原始文本。
 * @return string 替换后的文本。
 */
function fanimeta_footer_tokens( $text ) {
	$tokens = array(
		'{year}'      => gmdate( 'Y' ),
		'{site_name}' => esc_html( get_bloginfo( 'name' ) ),
		'{site_url}'  => esc_url( home_url( '/' ) ),
	);

	return strtr( $text, $tokens );
}
