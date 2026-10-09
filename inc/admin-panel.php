<?php
/**
 * 后台运营面板：内容审核工作流 + 站点概览看板。
 *
 * 这两块是给站长（administrator）日常运营用的：
 *
 *   内容审核 —— 前台投稿默认进 pending，在这里一键通过或驳回。
 *               驳回必须填原因，原因会随邮件发给作者，作者改稿后可重新提交。
 *   站点概览 —— 一屏看清用户 / 文章 / 评论 / 待办，并给出快捷入口。
 *
 * 依赖 inc/user-center.php 提供的状态映射与 meta 常量。
 *
 * @package Fanimeta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   公共辅助
   ============================================================ */

/**
 * 待审稿件数量（供菜单气泡使用）。
 *
 * @return int
 */
function fanimeta_pending_post_count() {
	$fanimeta_counts = wp_count_posts();
	return isset( $fanimeta_counts->pending ) ? (int) $fanimeta_counts->pending : 0;
}

/**
 * 取待审稿件。
 *
 * @param int $limit 上限，-1 为全部。
 * @return WP_Post[]
 */
function fanimeta_get_pending_posts( $limit = -1 ) {
	return get_posts(
		array(
			'post_type'   => 'post',
			'post_status' => 'pending',
			'numberposts' => $limit,
			'orderby'     => 'date',
			'order'       => 'ASC',
		)
	);
}

/**
 * 取被驳回的稿件（draft + 带驳回原因）。
 *
 * @param int $limit 上限。
 * @return WP_Post[]
 */
function fanimeta_get_rejected_posts( $limit = 20 ) {
	return get_posts(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'numberposts' => $limit,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => FANIMETA_META_REJECT_REASON,
					'compare' => 'EXISTS',
				),
			),
		)
	);
}

/**
 * 后台跳转回审核页并带提示。
 *
 * @param string $msg  消息键。
 * @param int    $id   相关文章。
 */
function fanimeta_review_redirect( $msg, $id = 0 ) {
	$fanimeta_args = array(
		'page'         => 'fanimeta-review',
		'fanimeta_msg' => $msg,
	);
	if ( $id ) {
		$fanimeta_args['fanimeta_post'] = (int) $id;
	}

	wp_safe_redirect( add_query_arg( $fanimeta_args, admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * 校验后台审核动作的公共前置：权限 + nonce + 目标文章。
 *
 * @param string $nonce_action nonce 动作名。
 * @param string $cap          要求的能力。
 * @return WP_Post 校验通过的文章对象（不通过则直接终止）。
 */
function fanimeta_review_guard( $nonce_action, $cap = 'manage_options' ) {
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( '无权限操作。', 'fanimeta' ) );
	}

	$fanimeta_post_id = isset( $_POST['fanimeta_post_id'] ) ? (int) $_POST['fanimeta_post_id'] : 0;
	check_admin_referer( $nonce_action . '_' . $fanimeta_post_id );

	$fanimeta_post = get_post( $fanimeta_post_id );
	if ( ! $fanimeta_post || 'post' !== $fanimeta_post->post_type ) {
		wp_die( esc_html__( '目标文章不存在。', 'fanimeta' ) );
	}

	return $fanimeta_post;
}

/* ============================================================
   邮件通知：稿件审核结果告知作者
   ============================================================ */

/**
 * 生成审核结果邮件的 HTML 正文。
 *
 * @param WP_Post $post   稿件。
 * @param string  $type   approved | rejected。
 * @param string  $reason 驳回原因。
 * @return string
 */
function fanimeta_review_email_body( $post, $type, $reason = '' ) {
	$fanimeta_site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$fanimeta_title = get_the_title( $post );
	$fanimeta_box   = 'background:#fff8e6;border:2px solid #2b2b2b;border-radius:10px;padding:20px;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;color:#2b2b2b;';

	if ( 'approved' === $type ) {
		$fanimeta_heading = '稿件已通过审核';
		$fanimeta_intro   = '你投稿的文章已通过审核并发布上线，感谢你的分享！';
		$fanimeta_action  = '<p style="margin:18px 0 0;"><a href="' . esc_url( get_permalink( $post ) ) . '" style="display:inline-block;background:#ffbe0b;color:#2b2b2b;font-weight:700;text-decoration:none;padding:10px 20px;border:2px solid #2b2b2b;border-radius:8px;">查看文章</a></p>';
		$fanimeta_extra   = '';
	} else {
		$fanimeta_heading = '稿件未通过审核';
		$fanimeta_intro   = '你投稿的文章未通过审核。请根据下方原因修改后重新提交。';
		$fanimeta_extra   = '<div style="margin:14px 0;padding:12px 14px;background:#fff;border-left:4px solid #dc3232;border-radius:4px;"><strong>驳回原因：</strong><br>' . nl2br( esc_html( $reason ) ) . '</div>';
		$fanimeta_action  = '<p style="margin:18px 0 0;"><a href="' . esc_url( fanimeta_submission_edit_url( $post->ID ) ) . '" style="display:inline-block;background:#ffbe0b;color:#2b2b2b;font-weight:700;text-decoration:none;padding:10px 20px;border:2px solid #2b2b2b;border-radius:8px;">修改并重新提交</a></p>';
	}

	return '<div style="' . $fanimeta_box . '">'
		. '<h2 style="margin:0 0 12px;font-size:18px;">' . esc_html( $fanimeta_heading ) . '</h2>'
		. '<p style="margin:0 0 8px;line-height:1.7;">' . esc_html( $fanimeta_intro ) . '</p>'
		. '<p style="margin:0;color:#8a8072;font-size:13px;">文章：' . esc_html( $fanimeta_title ) . '</p>'
		. $fanimeta_extra
		. $fanimeta_action
		. '<p style="margin:18px 0 0;color:#8a8072;font-size:12px;">此邮件由 ' . esc_html( $fanimeta_site ) . ' 自动发送。</p>'
		. '</div>';
}

/**
 * 给稿件作者发审核结果通知。
 *
 * 发信失败不阻塞审核流程 —— 审核结果已经落库，邮件只是通知。
 *
 * @param WP_Post $post   稿件。
 * @param string  $type   approved | rejected。
 * @param string  $reason 驳回原因。
 * @return bool
 */
function fanimeta_notify_author_review( $post, $type, $reason = '' ) {
	$fanimeta_author = get_userdata( $post->post_author );
	if ( ! $fanimeta_author || ! is_email( $fanimeta_author->user_email ) ) {
		return false;
	}

	$fanimeta_site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$fanimeta_subject = 'approved' === $type
		? sprintf( '【%s】你的稿件已通过审核', $fanimeta_site )
		: sprintf( '【%s】你的稿件需要修改', $fanimeta_site );

	return (bool) wp_mail(
		$fanimeta_author->user_email,
		$fanimeta_subject,
		fanimeta_review_email_body( $post, $type, $reason ),
		array( 'Content-Type: text/html; charset=UTF-8' )
	);
}

/* ============================================================
   内容审核：菜单
   ============================================================ */

/**
 * 注册「内容审核」菜单。
 */
function fanimeta_review_menu() {
	add_menu_page(
		__( '内容审核', 'fanimeta' ),
		__( '内容审核', 'fanimeta' ),
		'manage_options',
		'fanimeta-review',
		'fanimeta_review_page_render',
		'dashicons-yes-alt',
		70
	);
}
add_action( 'admin_menu', 'fanimeta_review_menu' );

/**
 * 在菜单标题上挂待审数量气泡。
 */
function fanimeta_review_menu_bubble() {
	$fanimeta_count = fanimeta_pending_post_count();
	if ( $fanimeta_count < 1 ) {
		return;
	}

	global $menu;
	foreach ( $menu as $fanimeta_i => $fanimeta_item ) {
		if ( isset( $fanimeta_item[2] ) && 'fanimeta-review' === $fanimeta_item[2] ) {
			$menu[ $fanimeta_i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $fanimeta_count . '</span></span>';
			break;
		}
	}
}
add_action( 'admin_menu', 'fanimeta_review_menu_bubble', 999 );

/* ============================================================
   内容审核：页面
   ============================================================ */

/**
 * 渲染内容审核页。
 */
function fanimeta_review_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限访问', 'fanimeta' ) );
	}

	$fanimeta_msg    = isset( $_GET['fanimeta_msg'] ) ? sanitize_key( wp_unslash( $_GET['fanimeta_msg'] ) ) : '';
	$fanimeta_pend   = fanimeta_get_pending_posts( -1 );
	$fanimeta_reject = fanimeta_get_rejected_posts( 20 );
	?>
	<div class="wrap fa-review">
		<h1><?php esc_html_e( '内容审核', 'fanimeta' ); ?></h1>

		<?php if ( $fanimeta_msg ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				switch ( $fanimeta_msg ) {
					case 'approved':
						esc_html_e( '已通过并发布，并已邮件通知作者。', 'fanimeta' );
						break;
					case 'rejected':
						esc_html_e( '已驳回，驳回原因已邮件通知作者。', 'fanimeta' );
						break;
					case 'restored':
						esc_html_e( '已重新放回审核队列。', 'fanimeta' );
						break;
					default:
						esc_html_e( '操作完成。', 'fanimeta' );
				}
				?>
			</p></div>
		<?php endif; ?>

		<h2 class="fa-review-section">
			<?php esc_html_e( '待审稿件', 'fanimeta' ); ?>
			<span class="fa-count-pill"><?php echo count( $fanimeta_pend ); ?></span>
		</h2>

		<?php if ( $fanimeta_pend ) : ?>
			<div class="fa-review-list">
				<?php foreach ( $fanimeta_pend as $fanimeta_post ) : ?>
					<?php
					$fanimeta_author = get_userdata( $fanimeta_post->post_author );
					$fanimeta_cats   = get_the_category( $fanimeta_post->ID );
					$fanimeta_cat    = $fanimeta_cats ? implode( '、', wp_list_pluck( $fanimeta_cats, 'name' ) ) : '';
					$fanimeta_chars  = fanimeta_content_char_count( $fanimeta_post->post_content );
					?>
					<div class="fa-review-card">
						<div class="fa-review-head">
							<h3 class="fa-review-title"><?php echo esc_html( get_the_title( $fanimeta_post ) ); ?></h3>
							<span class="fa-badge is-wait"><?php esc_html_e( '待审', 'fanimeta' ); ?></span>
						</div>

						<div class="fa-review-meta">
							<span class="fa-review-author">
								<?php echo get_avatar( $fanimeta_post->post_author, 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo esc_html( $fanimeta_author ? $fanimeta_author->display_name : '#' . $fanimeta_post->post_author ); ?>
							</span>
							<span><?php echo esc_html( get_the_date( 'Y-m-d H:i', $fanimeta_post ) ); ?></span>
							<?php if ( $fanimeta_cat ) : ?>
								<span><?php echo esc_html( $fanimeta_cat ); ?></span>
							<?php endif; ?>
							<span><?php echo esc_html( sprintf( __( '%s 字 · 约 %d 分钟', 'fanimeta' ), number_format_i18n( $fanimeta_chars ), fanimeta_content_read_minutes( $fanimeta_post->post_content ) ) ); ?></span>
						</div>

						<div class="fa-review-excerpt">
							<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $fanimeta_post->post_content ), 120, '…' ) ); ?>
						</div>

						<div class="fa-review-actions">
							<a class="button" href="<?php echo esc_url( get_preview_post_link( $fanimeta_post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '查看全文', 'fanimeta' ); ?></a>
							<a class="button" href="<?php echo esc_url( get_edit_post_link( $fanimeta_post->ID ) ); ?>"><?php esc_html_e( '后台编辑', 'fanimeta' ); ?></a>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fa-inline-form">
								<input type="hidden" name="action" value="fanimeta_review_approve">
								<input type="hidden" name="fanimeta_post_id" value="<?php echo (int) $fanimeta_post->ID; ?>">
								<?php wp_nonce_field( 'fanimeta_review_approve_' . $fanimeta_post->ID ); ?>
								<button type="submit" class="button button-primary"><?php esc_html_e( '通过并发布', 'fanimeta' ); ?></button>
							</form>

							<button type="button" class="button fa-reject-toggle" data-target="reject-<?php echo (int) $fanimeta_post->ID; ?>"><?php esc_html_e( '驳回', 'fanimeta' ); ?></button>
						</div>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fa-reject-form" id="reject-<?php echo (int) $fanimeta_post->ID; ?>" hidden>
							<input type="hidden" name="action" value="fanimeta_review_reject">
							<input type="hidden" name="fanimeta_post_id" value="<?php echo (int) $fanimeta_post->ID; ?>">
							<?php wp_nonce_field( 'fanimeta_review_reject_' . $fanimeta_post->ID ); ?>
							<label class="fa-reject-label" for="reason-<?php echo (int) $fanimeta_post->ID; ?>"><?php esc_html_e( '驳回原因（会随邮件发给作者，请写清楚）', 'fanimeta' ); ?></label>
							<textarea id="reason-<?php echo (int) $fanimeta_post->ID; ?>" name="fanimeta_reject_reason" rows="3" required placeholder="<?php esc_attr_e( '例如：正文缺少必要的说明，或图片与内容不符，请补充后重新提交。', 'fanimeta' ); ?>"></textarea>
							<p class="fa-reject-actions">
								<button type="submit" class="button"><?php esc_html_e( '确认驳回并发邮件', 'fanimeta' ); ?></button>
								<button type="button" class="button fa-reject-cancel" data-target="reject-<?php echo (int) $fanimeta_post->ID; ?>"><?php esc_html_e( '取消', 'fanimeta' ); ?></button>
							</p>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="fa-empty"><?php esc_html_e( '当前没有待审稿件。', 'fanimeta' ); ?></div>
		<?php endif; ?>

		<h2 class="fa-review-section">
			<?php esc_html_e( '已驳回', 'fanimeta' ); ?>
			<span class="fa-count-pill is-muted"><?php echo count( $fanimeta_reject ); ?></span>
		</h2>

		<?php if ( $fanimeta_reject ) : ?>
			<table class="widefat striped fa-reject-table">
				<thead>
					<tr>
						<th><?php esc_html_e( '标题', 'fanimeta' ); ?></th>
						<th style="width:130px"><?php esc_html_e( '作者', 'fanimeta' ); ?></th>
						<th style="width:150px"><?php esc_html_e( '驳回时间', 'fanimeta' ); ?></th>
						<th><?php esc_html_e( '原因', 'fanimeta' ); ?></th>
						<th style="width:110px"><?php esc_html_e( '操作', 'fanimeta' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fanimeta_reject as $fanimeta_post ) : ?>
						<?php
						$fanimeta_reason = (string) get_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECT_REASON, true );
						$fanimeta_at     = (string) get_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECTED_AT, true );
						$fanimeta_author = get_userdata( $fanimeta_post->post_author );
						?>
						<tr>
							<td><strong><?php echo esc_html( get_the_title( $fanimeta_post ) ); ?></strong></td>
							<td><?php echo esc_html( $fanimeta_author ? $fanimeta_author->display_name : '#' . $fanimeta_post->post_author ); ?></td>
							<td><?php echo esc_html( $fanimeta_at ? $fanimeta_at : get_the_date( 'Y-m-d', $fanimeta_post ) ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $fanimeta_reason, 30, '…' ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fa-inline-form">
									<input type="hidden" name="action" value="fanimeta_review_restore">
									<input type="hidden" name="fanimeta_post_id" value="<?php echo (int) $fanimeta_post->ID; ?>">
									<?php wp_nonce_field( 'fanimeta_review_restore_' . $fanimeta_post->ID ); ?>
									<button type="submit" class="button"><?php esc_html_e( '恢复待审', 'fanimeta' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="fa-empty"><?php esc_html_e( '暂无被驳回的稿件。', 'fanimeta' ); ?></div>
		<?php endif; ?>
	</div>

	<script>
	(function () {
		'use strict';
		// 驳回表单展开 / 收起
		document.addEventListener('click', function (e) {
			var t = e.target;
			if (t.classList && t.classList.contains('fa-reject-toggle')) {
				var box = document.getElementById(t.getAttribute('data-target'));
				if (box) { box.hidden = false; }
			}
			if (t.classList && t.classList.contains('fa-reject-cancel')) {
				var box2 = document.getElementById(t.getAttribute('data-target'));
				if (box2) { box2.hidden = true; }
			}
		});
	})();
	</script>
	<?php
}

/* ============================================================
   内容审核：动作处理
   ============================================================ */

/**
 * 通过并发布。
 */
function fanimeta_handle_review_approve() {
	$fanimeta_post = fanimeta_review_guard( 'fanimeta_review_approve' );

	wp_update_post(
		array(
			'ID'          => $fanimeta_post->ID,
			'post_status' => 'publish',
		)
	);

	update_post_meta( $fanimeta_post->ID, FANIMETA_META_REVIEWED_AT, current_time( 'mysql' ) );
	delete_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECT_REASON );
	delete_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECTED_AT );

	fanimeta_notify_author_review( get_post( $fanimeta_post->ID ), 'approved' );

	fanimeta_review_redirect( 'approved', $fanimeta_post->ID );
}
add_action( 'admin_post_fanimeta_review_approve', 'fanimeta_handle_review_approve' );

/**
 * 驳回（必须填原因）。
 */
function fanimeta_handle_review_reject() {
	$fanimeta_post = fanimeta_review_guard( 'fanimeta_review_reject' );

	$fanimeta_reason = isset( $_POST['fanimeta_reject_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fanimeta_reject_reason'] ) ) : '';

	if ( '' === trim( $fanimeta_reason ) ) {
		fanimeta_review_redirect( 'empty_reason', $fanimeta_post->ID );
	}

	// 退回 draft（而不是 pending）：draft 在「我的投稿」里才会显示为「未通过」，
	// 且作者能通过投稿页的编辑模式改稿重投。
	wp_update_post(
		array(
			'ID'          => $fanimeta_post->ID,
			'post_status' => 'draft',
		)
	);

	update_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECT_REASON, $fanimeta_reason );
	update_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECTED_AT, current_time( 'mysql' ) );

	fanimeta_notify_author_review( get_post( $fanimeta_post->ID ), 'rejected', $fanimeta_reason );

	fanimeta_review_redirect( 'rejected', $fanimeta_post->ID );
}
add_action( 'admin_post_fanimeta_review_reject', 'fanimeta_handle_review_reject' );

/**
 * 把已驳回的稿件恢复回待审队列。
 */
function fanimeta_handle_review_restore() {
	$fanimeta_post = fanimeta_review_guard( 'fanimeta_review_restore' );

	wp_update_post(
		array(
			'ID'          => $fanimeta_post->ID,
			'post_status' => 'pending',
		)
	);

	delete_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECT_REASON );
	delete_post_meta( $fanimeta_post->ID, FANIMETA_META_REJECTED_AT );

	fanimeta_review_redirect( 'restored', $fanimeta_post->ID );
}
add_action( 'admin_post_fanimeta_review_restore', 'fanimeta_handle_review_restore' );

/* ============================================================
   站点概览：菜单
   ============================================================ */

/**
 * 注册「站点概览」菜单。
 */
function fanimeta_dashboard_menu() {
	add_menu_page(
		__( '站点概览', 'fanimeta' ),
		__( '站点概览', 'fanimeta' ),
		'manage_options',
		'fanimeta-dashboard',
		'fanimeta_dashboard_page_render',
		'dashicons-chart-area',
		3
	);
}
add_action( 'admin_menu', 'fanimeta_dashboard_menu' );

/* ============================================================
   站点概览：统计
   ============================================================ */

/**
 * 取近 N 天每日新增数量（文章 / 用户）。
 *
 * @param int $days 天数。
 * @return array{posts:array,users:array,labels:array}
 */
function fanimeta_dashboard_trend( $days = 14 ) {
	global $wpdb;

	$days  = max( 1, min( 60, (int) $days ) );
	$since = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- 显式使用站点时区，确保趋势按站点日历日切分。

	$fanimeta_post_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE(post_date) AS d, COUNT(*) AS c
			 FROM {$wpdb->posts}
			 WHERE post_type = 'post' AND post_status = 'publish' AND post_date >= %s
			 GROUP BY DATE(post_date)",
			$since
		),
		OBJECT_K
	);

	$fanimeta_user_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE(user_registered) AS d, COUNT(*) AS c
			 FROM {$wpdb->users}
			 WHERE user_registered >= %s
			 GROUP BY DATE(user_registered)",
			$since
		),
		OBJECT_K
	);

	$fanimeta_posts = array();
	$fanimeta_users = array();
	$fanimeta_labels = array();

	for ( $fanimeta_i = $days - 1; $fanimeta_i >= 0; $fanimeta_i-- ) {
		$fanimeta_day  = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( $fanimeta_i * DAY_IN_SECONDS ) );
		$fanimeta_labels[] = $fanimeta_day;
		$fanimeta_posts[]  = isset( $fanimeta_post_rows[ $fanimeta_day ] ) ? (int) $fanimeta_post_rows[ $fanimeta_day ]->c : 0;
		$fanimeta_users[]  = isset( $fanimeta_user_rows[ $fanimeta_day ] ) ? (int) $fanimeta_user_rows[ $fanimeta_day ]->c : 0;
	}

	return array(
		'posts'  => $fanimeta_posts,
		'users'  => $fanimeta_users,
		'labels' => $fanimeta_labels,
	);
}

/**
 * 未认证且非站长的用户数。
 *
 * @return int
 */
function fanimeta_unverified_user_count() {
	$fanimeta_users = get_users( array( 'fields' => array( 'ID' ) ) );
	$fanimeta_n     = 0;
	foreach ( $fanimeta_users as $fanimeta_u ) {
		if ( fanimeta_is_owner( $fanimeta_u->ID ) ) {
			continue;
		}
		if ( ! get_user_meta( $fanimeta_u->ID, 'fanimeta_verified', true ) ) {
			++$fanimeta_n;
		}
	}
	return $fanimeta_n;
}

/* ============================================================
   站点概览：页面
   ============================================================ */

/**
 * 渲染站点概览页。
 */
function fanimeta_dashboard_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '无权限访问', 'fanimeta' ) );
	}

	$fanimeta_pc       = wp_count_posts();
	$fanimeta_publish  = isset( $fanimeta_pc->publish ) ? (int) $fanimeta_pc->publish : 0;
	$fanimeta_pending  = isset( $fanimeta_pc->pending ) ? (int) $fanimeta_pc->pending : 0;
	$fanimeta_draft    = isset( $fanimeta_pc->draft ) ? (int) $fanimeta_pc->draft : 0;
	$fanimeta_users    = count_users();
	$fanimeta_cc       = wp_count_comments();
	$fanimeta_total_c  = isset( $fanimeta_cc->total_comments ) ? (int) $fanimeta_cc->total_comments : 0;
	$fanimeta_moder    = isset( $fanimeta_cc->moderated ) ? (int) $fanimeta_cc->moderated : 0;
	$fanimeta_unverif  = fanimeta_unverified_user_count();
	$fanimeta_trend    = fanimeta_dashboard_trend( 14 );

	// 邀请码
	$fanimeta_codes    = fanimeta_get_invite_codes_raw();
	$fanimeta_code_all = is_array( $fanimeta_codes ) ? count( $fanimeta_codes ) : 0;
	$fanimeta_code_used = 0;
	if ( is_array( $fanimeta_codes ) ) {
		foreach ( $fanimeta_codes as $fanimeta_c ) {
			if ( ! empty( $fanimeta_c['used_by'] ) ) {
				++$fanimeta_code_used;
			}
		}
	}
	$fanimeta_code_free = max( 0, $fanimeta_code_all - $fanimeta_code_used );

	// 趋势峰值（用于柱状高度归一）
	$fanimeta_peak = 1;
	foreach ( $fanimeta_trend['posts'] as $fanimeta_v ) {
		$fanimeta_peak = max( $fanimeta_peak, (int) $fanimeta_v );
	}
	foreach ( $fanimeta_trend['users'] as $fanimeta_v ) {
		$fanimeta_peak = max( $fanimeta_peak, (int) $fanimeta_v );
	}
	?>
	<div class="wrap fa-dash">
		<h1><?php esc_html_e( '站点概览', 'fanimeta' ); ?></h1>

		<div class="fa-cards">
			<div class="fa-card">
				<span class="fa-card-num"><?php echo (int) $fanimeta_users['total_users']; ?></span>
				<span class="fa-card-label"><?php esc_html_e( '注册用户', 'fanimeta' ); ?></span>
			</div>
			<div class="fa-card">
				<span class="fa-card-num"><?php echo (int) $fanimeta_publish; ?></span>
				<span class="fa-card-label"><?php esc_html_e( '已发布文章', 'fanimeta' ); ?></span>
			</div>
			<div class="fa-card is-wait">
				<span class="fa-card-num"><?php echo (int) $fanimeta_pending; ?></span>
				<span class="fa-card-label"><?php esc_html_e( '待审稿件', 'fanimeta' ); ?></span>
			</div>
			<div class="fa-card">
				<span class="fa-card-num"><?php echo (int) $fanimeta_draft; ?></span>
				<span class="fa-card-label"><?php esc_html_e( '草稿', 'fanimeta' ); ?></span>
			</div>
			<div class="fa-card">
				<span class="fa-card-num"><?php echo (int) $fanimeta_total_c; ?></span>
				<span class="fa-card-label"><?php esc_html_e( '评论总数', 'fanimeta' ); ?></span>
			</div>
			<div class="fa-card">
				<span class="fa-card-num"><?php echo (int) $fanimeta_code_free; ?></span>
				<span class="fa-card-label"><?php esc_html_e( '可用邀请码', 'fanimeta' ); ?></span>
			</div>
		</div>

		<div class="fa-dash-grid">
			<div class="fa-panel">
				<h2><?php esc_html_e( '近 14 天新增', 'fanimeta' ); ?></h2>
				<div class="fa-chart" role="img" aria-label="<?php esc_attr_e( '近 14 天新增文章与用户柱状图', 'fanimeta' ); ?>">
					<?php foreach ( $fanimeta_trend['labels'] as $fanimeta_idx => $fanimeta_day ) : ?>
						<?php
						$fanimeta_pv = (int) $fanimeta_trend['posts'][ $fanimeta_idx ];
						$fanimeta_uv = (int) $fanimeta_trend['users'][ $fanimeta_idx ];
						$fanimeta_ph = (int) round( $fanimeta_pv / $fanimeta_peak * 100 );
						$fanimeta_uh = (int) round( $fanimeta_uv / $fanimeta_peak * 100 );
						?>
						<div class="fa-chart-col" title="<?php echo esc_attr( $fanimeta_day . '：文章 ' . $fanimeta_pv . ' / 用户 ' . $fanimeta_uv ); ?>">
							<div class="fa-chart-bars">
								<span class="fa-bar is-post" style="height:<?php echo (int) max( 2, $fanimeta_ph ); ?>%"></span>
								<span class="fa-bar is-user" style="height:<?php echo (int) max( 2, $fanimeta_uh ); ?>%"></span>
							</div>
							<span class="fa-chart-x"><?php echo esc_html( gmdate( 'm-d', strtotime( $fanimeta_day ) ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="fa-legend">
					<span class="fa-dot is-post"></span><?php esc_html_e( '新增文章', 'fanimeta' ); ?>
					<span class="fa-dot is-user"></span><?php esc_html_e( '新增用户', 'fanimeta' ); ?>
				</p>
			</div>

			<div class="fa-panel">
				<h2><?php esc_html_e( '待办', 'fanimeta' ); ?></h2>
				<ul class="fa-todo">
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanimeta-review' ) ); ?>">
							<span class="fa-todo-label"><?php esc_html_e( '待审稿件', 'fanimeta' ); ?></span>
							<span class="fa-todo-num <?php echo $fanimeta_pending ? 'is-alert' : 'is-zero'; ?>"><?php echo (int) $fanimeta_pending; ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ); ?>">
							<span class="fa-todo-label"><?php esc_html_e( '待审评论', 'fanimeta' ); ?></span>
							<span class="fa-todo-num <?php echo $fanimeta_moder ? 'is-alert' : 'is-zero'; ?>"><?php echo (int) $fanimeta_moder; ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanimeta-verify' ) ); ?>">
							<span class="fa-todo-label"><?php esc_html_e( '未认证用户', 'fanimeta' ); ?></span>
							<span class="fa-todo-num is-zero"><?php echo (int) $fanimeta_unverif; ?></span>
						</a>
					</li>
				</ul>

				<h2><?php esc_html_e( '快捷入口', 'fanimeta' ); ?></h2>
				<p class="fa-links">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=fanimeta-review' ) ); ?>"><?php esc_html_e( '去审核稿件', 'fanimeta' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( '写文章', 'fanimeta' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=fanimeta-verify' ) ); ?>"><?php esc_html_e( '生成邀请码', 'fanimeta' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=fanimeta-mail' ) ); ?>"><?php esc_html_e( '邮件服务', 'fanimeta' ); ?></a>
				</p>
			</div>
		</div>
	</div>
	<?php
}
