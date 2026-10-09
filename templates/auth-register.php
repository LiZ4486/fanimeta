<?php
/**
 * 注册页模板（路由：/register/）
 *
 * 字段顺序：账号昵称 → 账号邮箱 → 密码 → 确认密码 →（邮箱验证码）→ 邀请码
 * 校验规则与「昵称只能中英文数字 / 不可纯数字 / 不可重复 / 邀请码 / 邮箱验证码」
 * 全部复用 functions.php 中已有的 registration_errors 钩子。
 *
 * @package Fanimeta
 */

get_header();

$fanimeta_flash      = fanimeta_auth_take_flash();
$fanimeta_need_code  = fanimeta_register_email_verify_enabled();
?>

<main class="main main-auth" id="main" role="main">
	<div class="main-inner">
		<div class="auth-wrap">

			<div class="auth-card auth-card-wide">
				<header class="auth-head">
					<h1 class="auth-title"><?php esc_html_e( '注册', 'fanimeta' ); ?></h1>
					<p class="auth-sub"><?php echo esc_html( sprintf( __( '创建你的 %s 账号', 'fanimeta' ), get_bloginfo( 'name' ) ) ); ?></p>
				</header>

				<?php fanimeta_auth_notice( $fanimeta_flash ); ?>

				<form class="auth-form" method="post" action="<?php echo esc_url( fanimeta_auth_url( 'register' ) ); ?>" id="fanimeta-register-form">
					<?php wp_nonce_field( 'fanimeta_auth_register', 'fanimeta_auth_nonce' ); ?>
					<input type="hidden" name="fanimeta_auth_action" value="register">

					<label class="auth-field">
						<span class="auth-label"><?php esc_html_e( '账号昵称', 'fanimeta' ); ?></span>
						<input type="text" name="user_login" class="auth-input" value="<?php echo esc_attr( fanimeta_auth_old( $fanimeta_flash, 'user_login' ) ); ?>" autocomplete="username" required>
						<span class="auth-hint"><?php esc_html_e( '可用中文、英文、数字，不能包含特殊符号，也不能是纯数字', 'fanimeta' ); ?></span>
					</label>

					<label class="auth-field">
						<span class="auth-label"><?php esc_html_e( '账号邮箱', 'fanimeta' ); ?></span>
						<input type="email" name="user_email" id="fanimeta-reg-email" class="auth-input" value="<?php echo esc_attr( fanimeta_auth_old( $fanimeta_flash, 'user_email' ) ); ?>" autocomplete="email" required>
					</label>

					<label class="auth-field">
						<span class="auth-label"><?php esc_html_e( '密码', 'fanimeta' ); ?></span>
						<input type="password" name="fanimeta_password" class="auth-input" autocomplete="new-password" required>
						<span class="auth-hint"><?php esc_html_e( '至少 10 位，需同时包含字母和数字', 'fanimeta' ); ?></span>
					</label>

					<label class="auth-field">
						<span class="auth-label"><?php esc_html_e( '确认密码', 'fanimeta' ); ?></span>
						<input type="password" name="fanimeta_password_confirm" class="auth-input" autocomplete="new-password" required>
					</label>

					<?php if ( $fanimeta_need_code ) : ?>
						<div class="auth-field">
							<span class="auth-label"><?php esc_html_e( '邮箱验证码', 'fanimeta' ); ?></span>
							<div class="auth-code-row">
								<input type="text" name="fanimeta_email_code" class="auth-input" inputmode="numeric" maxlength="6" placeholder="<?php esc_attr_e( '6 位数字', 'fanimeta' ); ?>" autocomplete="off" required>
								<button type="button" class="auth-code-btn" id="fanimeta-send-code"><?php esc_html_e( '获取验证码', 'fanimeta' ); ?></button>
							</div>
							<span class="auth-code-tip" id="fanimeta-code-tip" aria-live="polite"></span>
						</div>
					<?php endif; ?>

					<label class="auth-field">
						<span class="auth-label"><?php esc_html_e( '邀请码', 'fanimeta' ); ?></span>
						<input type="text" name="fanimeta_invite_code" class="auth-input" value="<?php echo esc_attr( fanimeta_auth_old( $fanimeta_flash, 'fanimeta_invite_code' ) ); ?>" autocomplete="off" required>
					</label>

					<button type="submit" class="auth-submit"><?php esc_html_e( '注册', 'fanimeta' ); ?></button>
				</form>

				<div class="auth-links">
					<a href="<?php echo esc_url( fanimeta_auth_url( 'login' ) ); ?>"><?php esc_html_e( '已有账号？去登录', 'fanimeta' ); ?></a>
				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
