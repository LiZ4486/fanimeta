<?php
/**
 * 页面头部
 *
 * @package Fanimeta
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">
<meta name="theme-color" content="#f7b500">
<link rel="profile" href="https://gmpg.org/xfn/11">
<?php $fanimeta_favicon = get_template_directory_uri() . '/assets/images/favicon'; ?>
<link rel="icon" type="image/png" sizes="512x512" href="<?php echo esc_url( $fanimeta_favicon . '.png' ); ?>">
<link rel="icon" type="image/x-icon" href="<?php echo esc_url( $fanimeta_favicon . '.ico' ); ?>">
<link rel="shortcut icon" type="image/x-icon" href="<?php echo esc_url( $fanimeta_favicon . '.ico' ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $fanimeta_favicon . '.png' ); ?>">
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
/**
 * 开屏公告弹窗：首次进入展示，用户点击「我知道了」后写入 localStorage，
 * 此后不再重复出现。
 * 文案可在后台「外观 → 自定义」中调整，见 fanimeta_customize_register()。
 */
$fanimeta_notice_text = get_theme_mod( 'fanimeta_notice_text', '本网站属于同人二创网站，如有侵权此网站会立刻关停' );
if ( $fanimeta_notice_text ) :
	?>
	<div class="site-notice" id="site-notice" role="dialog" aria-modal="true" aria-labelledby="site-notice-title">
		<div class="site-notice-mask"></div>
		<div class="site-notice-dialog">
			<div class="site-notice-head">
				<span class="site-notice-icon" aria-hidden="true">!</span>
				<h2 class="site-notice-title" id="site-notice-title"><?php esc_html_e( '站点公告', 'fanimeta' ); ?></h2>
			</div>
			<div class="site-notice-body">
				<p><?php echo esc_html( $fanimeta_notice_text ); ?></p>
			</div>
			<div class="site-notice-foot">
				<button type="button" class="site-notice-btn" id="site-notice-confirm"><?php esc_html_e( '我知道了', 'fanimeta' ); ?></button>
			</div>
		</div>
	</div>
	<script>
		// 已确认过的用户不再展示（避免弹窗闪烁）
		(function () {
			try {
				if (localStorage.getItem('fanimeta_notice_confirmed') === '1') {
					var el = document.getElementById('site-notice');
					if (el) { el.style.display = 'none'; }
				}
			} catch (e) {}
		})();
	</script>
	<?php
endif;
?>

<?php
/**
 * 登录弹窗：未登录时随页输出，由 assets/js/auth.js 控制显示。
 * 未启用 JS 时「登录」按钮仍会正常跳转到 /login/ 独立页面。
 */
if ( ! is_user_logged_in() ) :
	?>
	<div class="auth-modal" id="fanimeta-auth-modal" hidden role="dialog" aria-modal="true" aria-labelledby="fanimeta-modal-title">
		<div class="auth-modal-mask"></div>
		<div class="auth-modal-dialog">
			<button type="button" class="auth-modal-close" aria-label="<?php esc_attr_e( '关闭', 'fanimeta' ); ?>">&times;</button>

			<header class="auth-head">
				<h2 class="auth-title" id="fanimeta-modal-title"><?php esc_html_e( '登录', 'fanimeta' ); ?></h2>
				<p class="auth-sub"><?php echo esc_html( sprintf( __( '欢迎回到 %s', 'fanimeta' ), get_bloginfo( 'name' ) ) ); ?></p>
			</header>

			<div class="auth-notice auth-notice-error" id="fanimeta-modal-tip" hidden></div>

			<form class="auth-form" id="fanimeta-modal-login-form" method="post" action="<?php echo esc_url( fanimeta_auth_url( 'login' ) ); ?>">
				<?php wp_nonce_field( 'fanimeta_auth_login', 'fanimeta_auth_nonce' ); ?>
				<input type="hidden" name="fanimeta_auth_action" value="login">

				<label class="auth-field">
					<span class="auth-label"><?php esc_html_e( '账号昵称或邮箱', 'fanimeta' ); ?></span>
					<input type="text" name="user_login" class="auth-input" autocomplete="username" required>
				</label>

				<label class="auth-field">
					<span class="auth-label"><?php esc_html_e( '密码', 'fanimeta' ); ?></span>
					<input type="password" name="user_password" class="auth-input" autocomplete="current-password" required>
				</label>

				<label class="auth-remember">
					<input type="checkbox" name="rememberme" value="1">
					<span><?php esc_html_e( '记住我', 'fanimeta' ); ?></span>
				</label>

				<button type="submit" class="auth-submit"><?php esc_html_e( '登录', 'fanimeta' ); ?></button>
			</form>

			<div class="auth-links">
				<a href="<?php echo esc_url( fanimeta_auth_url( 'lostpassword' ) ); ?>"><?php esc_html_e( '忘记密码？', 'fanimeta' ); ?></a>
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<a href="<?php echo esc_url( fanimeta_auth_url( 'register' ) ); ?>"><?php esc_html_e( '注册新账号', 'fanimeta' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
endif;
?>

<div class="container">

	<div class="headband"></div>

	<header class="site-header" itemscope itemtype="https://schema.org/WPHeader">
		<div class="header-inner">
			<div class="site-nav-toggle">
				<span class="toggle-line toggle-line-first"></span>
				<span class="toggle-line toggle-line-middle"></span>
				<span class="toggle-line toggle-line-last"></span>
			</div>

			<?php if ( has_custom_logo() ) : ?>
				<div class="header-logo">
					<?php the_custom_logo(); ?>
				</div>
			<?php endif; ?>

			<nav class="site-nav" itemscope itemtype="https://schema.org/SiteNavigationElement">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_class'     => 'menu',
						'container'      => false,
						'fallback_cb'    => 'fanimeta_default_menu',
						'depth'          => 2,
					)
				);
				?>
			</nav>

			<div class="user-nav">
				<?php if ( is_user_logged_in() ) : ?>
					<?php $fanimeta_user = wp_get_current_user(); ?>
					<a class="user-avatar" href="<?php echo esc_url( fanimeta_get_profile_page_url() ); ?>" title="<?php esc_attr_e( '个人主页', 'fanimeta' ); ?>">
						<?php echo get_avatar( $fanimeta_user->ID, 32 ); ?>
						<?php echo fanimeta_avatar_verify_html( $fanimeta_user->ID ); // 站长认证角标 ?>
					</a>
					<a class="user-btn" href="<?php echo esc_url( fanimeta_get_profile_page_url() ); ?>"><?php esc_html_e( '个人主页', 'fanimeta' ); ?></a>
					<a class="user-btn user-btn-primary" href="<?php echo esc_url( fanimeta_get_submit_page_url() ); ?>"><?php esc_html_e( '投稿', 'fanimeta' ); ?></a>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<a class="user-btn" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( '后台', 'fanimeta' ); ?></a>
					<?php endif; ?>
					<a class="user-btn" href="<?php echo esc_url( fanimeta_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( '退出', 'fanimeta' ); ?></a>
				<?php else : ?>
					<a class="user-btn user-btn-primary" data-auth-open="login" href="<?php echo esc_url( fanimeta_login_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( '登录', 'fanimeta' ); ?></a>
					<?php if ( get_option( 'users_can_register' ) ) : ?>
						<a class="user-btn" href="<?php echo esc_url( fanimeta_register_url() ); ?>"><?php esc_html_e( '注册', 'fanimeta' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</header>
