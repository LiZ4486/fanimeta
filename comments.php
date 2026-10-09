<?php
/**
 * 评论模板
 *
 * @package Fanimeta
 */

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$fanimeta_comment_count = get_comments_number();
			if ( '1' === $fanimeta_comment_count ) {
				esc_html_e( '1 条评论', 'fanimeta' );
			} else {
				printf(
					/* translators: %s: 评论数量 */
					esc_html( _n( '%s 条评论', '%s 条评论', (int) $fanimeta_comment_count, 'fanimeta' ) ),
					esc_html( number_format_i18n( $fanimeta_comment_count ) )
				);
			}
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation();
		?>

		<?php if ( ! comments_open() ) : ?>
			<p class="no-comments"><?php esc_html_e( '评论已关闭。', 'fanimeta' ); ?></p>
		<?php endif; ?>

	<?php endif; ?>

	<?php
	if ( ! comments_open() ) {
		// 评论已关闭时不显示表单与登录引导
	} elseif ( is_user_logged_in() ) {
		comment_form(
			array(
				'title_reply' => __( '发表评论', 'fanimeta' ),
			)
		);
	} else {
		?>
		<div class="comment-login-required">
			<p class="comment-login-text"><?php esc_html_e( '登录后才能发表评论，快来和大家一起交流吧！', 'fanimeta' ); ?></p>
			<div class="comment-login-actions">
				<a class="btn" data-auth-open="login" href="<?php echo esc_url( fanimeta_login_url( get_permalink() ) ); ?>"><?php esc_html_e( '登录', 'fanimeta' ); ?></a>
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<a class="btn btn-outline" href="<?php echo esc_url( fanimeta_register_url() ); ?>"><?php esc_html_e( '注册', 'fanimeta' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
	?>

</div>
