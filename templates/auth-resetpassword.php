<?php
/**
 * 重置密码页模板（路由：/reset-password/?key=xxx&login=yyy）
 *
 * @package Fanimeta
 */

get_header();

$fanimeta_flash = fanimeta_auth_take_flash();

$fanimeta_key   = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
$fanimeta_login = isset( $_GET['login'] ) ? (string) wp_unslash( $_GET['login'] ) : '';

$fanimeta_user = check_password_reset_key( $fanimeta_key, $fanimeta_login );
$fanimeta_ok   = ! is_wp_error( $fanimeta_user );
?>

<main class="main main-auth" id="main" role="main">
	<div class="main-inner">
		<div class="auth-wrap">

			<div class="auth-card">
				<header class="auth-head">
					<h1 class="auth-title"><?php esc_html_e( '设置新密码', 'fanimeta' ); ?></h1>
					<?php if ( $fanimeta_ok ) : ?>
						<p class="auth-sub"><?php echo esc_html( sprintf( __( '正在为「%s」设置新密码', 'fanimeta' ), $fanimeta_user->display_name ) ); ?></p>
					<?php endif; ?>
				</header>

				<?php fanimeta_auth_notice( $fanimeta_flash ); ?>

				<?php if ( ! $fanimeta_ok ) : ?>

					<div class="auth-notice auth-notice-error" role="alert"><?php esc_html_e( '重置链接无效或已过期，请重新申请。', 'fanimeta' ); ?></div>

					<div class="auth-links">
						<a href="<?php echo esc_url( fanimeta_auth_url( 'lostpassword' ) ); ?>"><?php esc_html_e( '重新申请重置链接', 'fanimeta' ); ?></a>
						<a href="<?php echo esc_url( fanimeta_auth_url( 'login' ) ); ?>"><?php esc_html_e( '返回登录', 'fanimeta' ); ?></a>
					</div>

				<?php else : ?>

					<form class="auth-form" method="post" action="<?php echo esc_url( fanimeta_auth_url( 'resetpassword' ) ); ?>">
						<?php wp_nonce_field( 'fanimeta_auth_resetpassword', 'fanimeta_auth_nonce' ); ?>
						<input type="hidden" name="fanimeta_auth_action" value="resetpassword">
						<input type="hidden" name="rp_key" value="<?php echo esc_attr( $fanimeta_key ); ?>">
						<input type="hidden" name="rp_login" value="<?php echo esc_attr( $fanimeta_login ); ?>">

						<label class="auth-field">
							<span class="auth-label"><?php esc_html_e( '新密码', 'fanimeta' ); ?></span>
							<input type="password" name="pass1" class="auth-input" autocomplete="new-password" required>
							<span class="auth-hint"><?php esc_html_e( '至少 10 位，需同时包含字母和数字', 'fanimeta' ); ?></span>
						</label>

						<label class="auth-field">
							<span class="auth-label"><?php esc_html_e( '确认新密码', 'fanimeta' ); ?></span>
							<input type="password" name="pass2" class="auth-input" autocomplete="new-password" required>
						</label>

						<button type="submit" class="auth-submit"><?php esc_html_e( '保存新密码', 'fanimeta' ); ?></button>
					</form>

					<div class="auth-links">
						<a href="<?php echo esc_url( fanimeta_auth_url( 'login' ) ); ?>"><?php esc_html_e( '返回登录', 'fanimeta' ); ?></a>
					</div>

				<?php endif; ?>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
