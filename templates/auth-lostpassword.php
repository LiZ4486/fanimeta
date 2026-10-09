<?php
/**
 * 找回密码页模板（路由：/lost-password/）
 *
 * @package Fanimeta
 */

get_header();

$fanimeta_flash = fanimeta_auth_take_flash();
?>

<main class="main main-auth" id="main" role="main">
	<div class="main-inner">
		<div class="auth-wrap">

			<div class="auth-card">
				<header class="auth-head">
					<h1 class="auth-title"><?php esc_html_e( '找回密码', 'fanimeta' ); ?></h1>
					<p class="auth-sub"><?php esc_html_e( '填写注册时使用的昵称或邮箱，我们会把重置链接发到对应邮箱。', 'fanimeta' ); ?></p>
				</header>

				<?php fanimeta_auth_notice( $fanimeta_flash ); ?>

				<form class="auth-form" method="post" action="<?php echo esc_url( fanimeta_auth_url( 'lostpassword' ) ); ?>">
					<?php wp_nonce_field( 'fanimeta_auth_lostpassword', 'fanimeta_auth_nonce' ); ?>
					<input type="hidden" name="fanimeta_auth_action" value="lostpassword">

					<label class="auth-field">
						<span class="auth-label"><?php esc_html_e( '账号昵称或邮箱', 'fanimeta' ); ?></span>
						<input type="text" name="user_login" class="auth-input" value="<?php echo esc_attr( fanimeta_auth_old( $fanimeta_flash, 'user_login' ) ); ?>" autocomplete="username" required>
					</label>

					<button type="submit" class="auth-submit"><?php esc_html_e( '发送重置链接', 'fanimeta' ); ?></button>
				</form>

				<div class="auth-links">
					<a href="<?php echo esc_url( fanimeta_auth_url( 'login' ) ); ?>"><?php esc_html_e( '返回登录', 'fanimeta' ); ?></a>
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
