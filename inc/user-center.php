<?php
/**
 * 用户中心：投稿状态与「我的投稿」区块。
 *
 * 前台投稿流程的状态闭环：
 *
 *   作者投稿 → pending（审核中） → 站长在后台审核
 *                                   ├─ 通过 → publish（已发布）
 *                                   └─ 驳回 → draft + 驳回原因（未通过，可编辑后重新提交）
 *
 * 本文件只负责「把状态讲清楚」：状态映射、统计、列表渲染。
 * 真正执行通过 / 驳回落库的是后台的 inc/admin-panel.php。
 *
 * @package Fanimeta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 驳回原因（post meta） */
if ( ! defined( 'FANIMETA_META_REJECT_REASON' ) ) {
	define( 'FANIMETA_META_REJECT_REASON', '_fanimeta_reject_reason' );
}

/** 驳回时间（post meta） */
if ( ! defined( 'FANIMETA_META_REJECTED_AT' ) ) {
	define( 'FANIMETA_META_REJECTED_AT', '_fanimeta_rejected_at' );
}

/** 审核备注 / 通过时间（post meta，仅后台可见） */
if ( ! defined( 'FANIMETA_META_REVIEWED_AT' ) ) {
	define( 'FANIMETA_META_REVIEWED_AT', '_fanimeta_reviewed_at' );
}

/**
 * 把一篇文章翻译成「投稿中心」视角下的状态。
 *
 * 注意 draft 有两种含义：一是作者自己没写完的草稿，二是被驳回后退回来的稿子。
 * 两者靠是否带驳回原因区分 —— 作者需要看到的文案完全不同。
 *
 * @param int|WP_Post $post 文章。
 * @return array{key:string,label:string,tone:string,note:string}
 */
function fanimeta_post_review_state( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array(
			'key'   => 'unknown',
			'label' => __( '未知', 'fanimeta' ),
			'tone'  => 'muted',
			'note'  => '',
		);
	}

	$fanimeta_reason = (string) get_post_meta( $post->ID, FANIMETA_META_REJECT_REASON, true );

	switch ( $post->post_status ) {
		case 'publish':
			return array(
				'key'   => 'publish',
				'label' => __( '已发布', 'fanimeta' ),
				'tone'  => 'ok',
				'note'  => '',
			);

		case 'pending':
			return array(
				'key'   => 'pending',
				'label' => __( '审核中', 'fanimeta' ),
				'tone'  => 'wait',
				'note'  => __( '已提交，等待站长审核。', 'fanimeta' ),
			);

		case 'draft':
			if ( '' !== $fanimeta_reason ) {
				return array(
					'key'   => 'rejected',
					'label' => __( '未通过', 'fanimeta' ),
					'tone'  => 'bad',
					'note'  => $fanimeta_reason,
				);
			}
			return array(
				'key'   => 'draft',
				'label' => __( '草稿', 'fanimeta' ),
				'tone'  => 'muted',
				'note'  => __( '尚未提交审核。', 'fanimeta' ),
			);

		case 'private':
			return array(
				'key'   => 'private',
				'label' => __( '私密', 'fanimeta' ),
				'tone'  => 'muted',
				'note'  => '',
			);

		case 'future':
			return array(
				'key'   => 'future',
				'label' => __( '定时发布', 'fanimeta' ),
				'tone'  => 'wait',
				'note'  => get_the_date( 'Y-m-d H:i', $post ),
			);

		case 'trash':
			return array(
				'key'   => 'trash',
				'label' => __( '回收站', 'fanimeta' ),
				'tone'  => 'muted',
				'note'  => '',
			);
	}

	return array(
		'key'   => $post->post_status,
		'label' => $post->post_status,
		'tone'  => 'muted',
		'note'  => '',
	);
}

/**
 * 统计正文实际字数（去掉 HTML 标签与空白后按字符计）。
 * 中英文混排下按字符数最贴近「目测长度」。
 *
 * @param string $content 正文。
 * @return int
 */
function fanimeta_content_char_count( $content ) {
	$fanimeta_text = wp_strip_all_tags( (string) $content );
	$fanimeta_text = preg_replace( '/\s+/u', '', $fanimeta_text );
	return (int) mb_strlen( (string) $fanimeta_text );
}

/**
 * 估算阅读时长（分钟），按中文 400 字/分钟。
 *
 * @param string $content 正文。
 * @return int
 */
function fanimeta_content_read_minutes( $content ) {
	$fanimeta_chars = fanimeta_content_char_count( $content );
	return max( 1, (int) ceil( $fanimeta_chars / 400 ) );
}

/**
 * 取某个用户的投稿列表。
 *
 * @param int  $user_id             用户 ID。
 * @param bool $include_unpublished 是否包含未发布内容。他人主页只能看已发布的。
 * @return WP_Post[]
 */
function fanimeta_get_user_submissions( $user_id, $include_unpublished = true ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return array();
	}

	// trash 一律排除：回收站里的东西不该出现在作者的投稿列表里，
	// 否则作者会以为自己还有一篇待处理。
	if ( $include_unpublished ) {
		$fanimeta_statuses = array( 'publish', 'pending', 'draft', 'private', 'future' );
	} else {
		$fanimeta_statuses = array( 'publish' );
	}

	return get_posts(
		array(
			'author'        => $user_id,
			'post_type'     => 'post',
			'post_status'   => $fanimeta_statuses,
			'numberposts'   => -1,
			'orderby'       => 'date',
			'order'         => 'DESC',
			'no_found_rows' => true,
		)
	);
}

/**
 * 汇总某个用户的投稿统计。
 *
 * @param int $user_id 用户 ID。
 * @return array{total:int,publish:int,pending:int,rejected:int,draft:int}
 */
function fanimeta_user_submission_summary( $user_id ) {
	$fanimeta_posts = fanimeta_get_user_submissions( $user_id, true );
	$fanimeta_out   = array(
		'total'    => 0,
		'publish'  => 0,
		'pending'  => 0,
		'rejected' => 0,
		'draft'    => 0,
	);

	foreach ( $fanimeta_posts as $fanimeta_p ) {
		++$fanimeta_out['total'];
		$fanimeta_state = fanimeta_post_review_state( $fanimeta_p );
		if ( isset( $fanimeta_out[ $fanimeta_state['key'] ] ) ) {
			++$fanimeta_out[ $fanimeta_state['key'] ];
		}
	}

	return $fanimeta_out;
}

/**
 * 生成「编辑并重新提交」链接（回到投稿页的编辑模式）。
 *
 * @param int $post_id 文章 ID。
 * @return string
 */
function fanimeta_submission_edit_url( $post_id ) {
	return add_query_arg( 'edit', (int) $post_id, fanimeta_get_submit_page_url() );
}

/**
 * 若本次请求带上了特色图，则替换文章的封面。
 *
 * 供投稿页在「新建」与「改稿重投」两条路径上共用。
 *
 * @param int $post_id 文章 ID。
 */
function fanimeta_maybe_replace_thumbnail( $post_id ) {
	if ( empty( $_FILES['post_thumbnail']['name'] ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$fanimeta_attachment_id = media_handle_upload( 'post_thumbnail', $post_id );
	if ( is_wp_error( $fanimeta_attachment_id ) ) {
		return;
	}

	$fanimeta_old = get_post_thumbnail_id( $post_id );
	if ( $fanimeta_old ) {
		wp_delete_attachment( (int) $fanimeta_old, true );
	}
	set_post_thumbnail( $post_id, $fanimeta_attachment_id );
}

/**
 * 渲染「我的投稿」区块。
 *
 * 查看自己：列出全部状态 + 顶部统计条 + 每项操作（预览 / 编辑重投）。
 * 查看他人：只列已发布，保持原来的简洁展示。
 *
 * @param int  $user_id 被查看的用户。
 * @param bool $is_self 是否查看自己。
 */
function fanimeta_render_submission_center( $user_id, $is_self ) {
	$user_id        = (int) $user_id;
	$fanimeta_posts = fanimeta_get_user_submissions( $user_id, (bool) $is_self );

	if ( ! $is_self ) {
		// ── 他人主页：只展示已发布文章 ──
		?>
		<section class="profile-posts">
			<h3 class="widget-title"><?php esc_html_e( 'TA 发布的文章', 'fanimeta' ); ?></h3>
			<?php if ( $fanimeta_posts ) : ?>
				<p class="profile-post-count"><?php echo esc_html( sprintf( __( '共 %d 篇', 'fanimeta' ), count( $fanimeta_posts ) ) ); ?></p>
				<ul class="profile-post-list">
					<?php foreach ( $fanimeta_posts as $fanimeta_post ) : ?>
						<li class="profile-post-item">
							<a class="profile-post-title" href="<?php echo esc_url( get_permalink( $fanimeta_post ) ); ?>"><?php echo esc_html( get_the_title( $fanimeta_post ) ); ?></a>
							<span class="profile-post-date"><?php echo esc_html( get_the_date( 'Y-m-d', $fanimeta_post ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="hint"><?php esc_html_e( 'TA 还没有发布过文章。', 'fanimeta' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return;
	}

	// ── 自己的主页：完整投稿中心 ──
	$fanimeta_summary = fanimeta_user_submission_summary( $user_id );
	?>
	<section class="profile-posts submission-center">
		<h3 class="widget-title"><?php esc_html_e( '我的投稿', 'fanimeta' ); ?></h3>

		<?php if ( $fanimeta_summary['total'] > 0 ) : ?>
			<div class="submission-summary">
				<span class="submission-stat">
					<strong><?php echo (int) $fanimeta_summary['total']; ?></strong>
					<em><?php esc_html_e( '全部', 'fanimeta' ); ?></em>
				</span>
				<span class="submission-stat is-ok">
					<strong><?php echo (int) $fanimeta_summary['publish']; ?></strong>
					<em><?php esc_html_e( '已发布', 'fanimeta' ); ?></em>
				</span>
				<span class="submission-stat is-wait">
					<strong><?php echo (int) $fanimeta_summary['pending']; ?></strong>
					<em><?php esc_html_e( '审核中', 'fanimeta' ); ?></em>
				</span>
				<span class="submission-stat is-bad">
					<strong><?php echo (int) $fanimeta_summary['rejected']; ?></strong>
					<em><?php esc_html_e( '未通过', 'fanimeta' ); ?></em>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $fanimeta_posts ) : ?>
			<ul class="submission-list">
				<?php
				foreach ( $fanimeta_posts as $fanimeta_post ) :
					$fanimeta_state = fanimeta_post_review_state( $fanimeta_post );
					$fanimeta_chars = fanimeta_content_char_count( $fanimeta_post->post_content );
					$fanimeta_cats  = get_the_category( $fanimeta_post->ID );
					$fanimeta_cat   = $fanimeta_cats ? $fanimeta_cats[0]->name : '';
					$fanimeta_is_pub = ( 'publish' === $fanimeta_post->post_status );
					?>
					<li class="submission-item is-<?php echo esc_attr( $fanimeta_state['key'] ); ?>">
						<div class="submission-head">
							<?php if ( $fanimeta_is_pub ) : ?>
								<a class="submission-title" href="<?php echo esc_url( get_permalink( $fanimeta_post ) ); ?>"><?php echo esc_html( get_the_title( $fanimeta_post ) ); ?></a>
							<?php else : ?>
								<span class="submission-title"><?php echo esc_html( get_the_title( $fanimeta_post ) ); ?></span>
							<?php endif; ?>
							<span class="submission-badge is-<?php echo esc_attr( $fanimeta_state['tone'] ); ?>"><?php echo esc_html( $fanimeta_state['label'] ); ?></span>
						</div>

						<div class="submission-meta">
							<span><?php echo esc_html( get_the_date( 'Y-m-d H:i', $fanimeta_post ) ); ?></span>
							<?php if ( $fanimeta_cat ) : ?>
								<span><?php echo esc_html( $fanimeta_cat ); ?></span>
							<?php endif; ?>
							<?php if ( $fanimeta_chars ) : ?>
								<span><?php echo esc_html( sprintf( __( '%s 字', 'fanimeta' ), number_format_i18n( $fanimeta_chars ) ) ); ?></span>
							<?php endif; ?>
						</div>

						<?php if ( '' !== $fanimeta_state['note'] ) : ?>
							<p class="submission-note is-<?php echo esc_attr( $fanimeta_state['tone'] ); ?>">
								<?php if ( 'bad' === $fanimeta_state['tone'] ) : ?>
									<strong><?php esc_html_e( '驳回原因：', 'fanimeta' ); ?></strong>
								<?php endif; ?>
								<?php echo esc_html( $fanimeta_state['note'] ); ?>
							</p>
						<?php endif; ?>

						<div class="submission-actions">
							<?php if ( $fanimeta_is_pub ) : ?>
								<a class="submission-btn" href="<?php echo esc_url( get_permalink( $fanimeta_post ) ); ?>"><?php esc_html_e( '查看文章', 'fanimeta' ); ?></a>
							<?php else : ?>
								<a class="submission-btn" href="<?php echo esc_url( get_preview_post_link( $fanimeta_post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '预览', 'fanimeta' ); ?></a>
								<a class="submission-btn is-primary" href="<?php echo esc_url( fanimeta_submission_edit_url( $fanimeta_post->ID ) ); ?>">
									<?php echo 'rejected' === $fanimeta_state['key'] ? esc_html__( '修改后重新提交', 'fanimeta' ) : esc_html__( '继续编辑', 'fanimeta' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="hint"><?php esc_html_e( '你还没有投稿。点顶部「投稿」开始分享吧。', 'fanimeta' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}
