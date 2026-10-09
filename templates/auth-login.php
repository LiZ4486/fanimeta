<?php
/**
 * 登录页模板（路由：/login/）
 *
 * @package Fanimeta
 */

get_header();

$fanimeta_flash    = fanimeta_auth_take_flash();
$fanimeta_redirect = fanimeta_auth_safe_redirect( isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '' );
?>

<main class="main main-auth" id="main" role="main">
	<div class="main-inner">
		<div class="auth-wrap">

			<div class="auth-card">
				<header class="auth-head">
					<h1 class="auth-title"><?php esc_html_e( '登录', 'fanimeta' ); ?></h1>
					<p class="auth-sub"><?php echo esc_html( sprintf( __( '欢迎回到 %s', 'fanimeta' ), get_bloginfo( 'name' ) ) ); ?></p>
				</header>

				<?php fanimeta_auth_notice( $fanimeta_flash ); ?>

				<form class="auth-form" method="post" action="<?php echo esc_url( fanimeta_auth_url( 'login' ) ); ?>">
					<?php wp_nonce_field( 'fanimeta_auth_login', 'fanimeta_auth_nonce' ); ?>
					<input type="hidden" name="fanimeta_auth_action" value="login">
					<?php if ( $fanimeta_redirect ) : ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $fanimeta_redirect ); ?>">
					<?php endif; ?>

					<label class="auth-field">
						<span class="auth-label"><?php esc_html_e( '账号昵称或邮箱', 'fanimeta' ); ?></span>
						<input type="text" name="user_login" class="auth-input" value="<?php echo esc_attr( fanimeta_auth_old( $fanimeta_flash, 'user_login' ) ); ?>" autocomplete="username" required>
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
	</div>
</main>

<?php
get_footer();
