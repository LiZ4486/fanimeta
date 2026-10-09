<?php
/**
 * 个人主页模板
 *
 * Template Name: 个人主页
 *
 * 支持：
 * - 查看任意用户的公开主页（?uid=2 或 ?user=昵称/登录名）
 * - 按 UID 或昵称搜索用户
 * - 查看自己时：可自定义昵称（不能含符号）、上传头像
 * - 查看他人时：只读展示其头像、昵称、UID、认证与发布过的文章
 *
 * @package Fanimeta
 */

$fanimeta_error          = '';
$fanimeta_success        = false;
$fanimeta_avatar_success = false;
$fanimeta_not_found      = false;

// 从投稿页跳回时携带的提示（投稿成功 / 更新成功）
$fanimeta_submitted = isset( $_GET['fanimeta_submitted'] ) ? sanitize_key( wp_unslash( $_GET['fanimeta_submitted'] ) ) : '';

// 1. 确定要查看哪个用户（必须在任何 HTML 输出之前）
$fanimeta_view_user = false;

if ( isset( $_GET['uid'] ) && ctype_digit( (string) $_GET['uid'] ) ) {
	$fanimeta_view_user = get_user_by( 'id', (int) $_GET['uid'] );
} elseif ( isset( $_GET['user'] ) && '' !== trim( (string) $_GET['user'] ) ) {
	$fanimeta_view_user = fanimeta_find_user( wp_unslash( $_GET['user'] ) );
} elseif ( is_user_logged_in() ) {
	$fanimeta_view_user = wp_get_current_user();
}

if ( ! $fanimeta_view_user ) {
	$fanimeta_not_found = true;
}

// 是否查看自己（决定是否显示编辑表单）
$fanimeta_is_self = ( $fanimeta_view_user && is_user_logged_in() && (int) $fanimeta_view_user->ID === (int) get_current_user_id() );

// 2. 处理头像上传（仅自己；必须在任何 HTML 输出之前）
if ( $fanimeta_is_self && isset( $_POST['fanimeta_update_avatar'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fanimeta_update_avatar' ) ) {
		$fanimeta_error = __( '安全校验失败，请刷新页面后重试。', 'fanimeta' );
	} elseif ( empty( $_FILES['avatar'] ) || empty( $_FILES['avatar']['name'] ) ) {
		$fanimeta_error = __( '请选择要上传的图片。', 'fanimeta' );
	} elseif ( is_wp_error( $fanimeta_avatar_limit = fanimeta_rate_limit( 'avatar_upload', FANIMETA_UPLOAD_MAX_PER_HOUR, HOUR_IN_SECONDS ) ) ) {
		// 上传频次限制：避免被当成免费图床无限灌文件。
		$fanimeta_error = $fanimeta_avatar_limit->get_error_message();
	} else {
		$fanimeta_file_type = isset( $_FILES['avatar']['type'] ) ? sanitize_text_field( wp_unslash( $_FILES['avatar']['type'] ) ) : '';
		if ( '' !== $fanimeta_file_type && 0 !== strpos( $fanimeta_file_type, 'image/' ) ) {
			$fanimeta_error = __( '请上传图片文件（jpg / png / gif / webp 等）。', 'fanimeta' );
		} else {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$fanimeta_user_id       = get_current_user_id();
			$fanimeta_attachment_id = media_handle_upload( 'avatar', 0 );

			if ( is_wp_error( $fanimeta_attachment_id ) ) {
				$fanimeta_error = $fanimeta_attachment_id->get_error_message();
			} else {
				$fanimeta_old_avatar = get_user_meta( $fanimeta_user_id, 'fanimeta_avatar', true );
				if ( $fanimeta_old_avatar ) {
					wp_delete_attachment( (int) $fanimeta_old_avatar, true );
				}
				update_user_meta( $fanimeta_user_id, 'fanimeta_avatar', $fanimeta_attachment_id );
				$fanimeta_avatar_success = true;
			}
		}
	}
}

// 3. 处理昵称修改（仅自己；必须在任何 HTML 输出之前）
if ( $fanimeta_is_self && isset( $_POST['fanimeta_update_nickname'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fanimeta_update_nickname' ) ) {
		$fanimeta_error = __( '安全校验失败，请刷新页面后重试。', 'fanimeta' );
	} else {
		$fanimeta_nickname = isset( $_POST['nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['nickname'] ) ) : '';

		if ( '' === $fanimeta_nickname ) {
			$fanimeta_error = __( '昵称不能为空。', 'fanimeta' );
		} elseif ( mb_strlen( $fanimeta_nickname ) > 20 ) {
			$fanimeta_error = __( '昵称过长，最多 20 个字符。', 'fanimeta' );
		} elseif ( ! preg_match( '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $fanimeta_nickname ) ) {
			$fanimeta_error = __( '昵称只能包含中文、字母、数字或下划线，不能包含符号。', 'fanimeta' );
		} elseif ( fanimeta_nickname_taken( $fanimeta_nickname, get_current_user_id() ) ) {
			// 昵称唯一性：否则任何用户都能改成站长的昵称，在评论区冒名发言。
			// 站长徽章绑的是 UID 不会跟着变，但普通访客只看显示名，分辨不出来。
			$fanimeta_error = __( '该昵称已被其他人使用，请换一个。', 'fanimeta' );
		} else {
			$fanimeta_user_id = get_current_user_id();
			$fanimeta_result  = wp_update_user(
				array(
					'ID'           => $fanimeta_user_id,
					'nickname'     => $fanimeta_nickname,
					'display_name' => $fanimeta_nickname,
				)
			);

			if ( is_wp_error( $fanimeta_result ) ) {
				$fanimeta_error = $fanimeta_result->get_error_message();
			} else {
				$fanimeta_success = true;
			}
		}
	}
}

get_header();
?>

<main class="main" id="main" role="main">
	<div class="main-inner">
		<div class="main-layout">

			<?php get_sidebar(); ?>

			<div class="content-wrap">
				<div class="content">

					<article class="post-block profile-page">

						<header class="post-header">
							<h1 class="post-title">
								<?php
								if ( $fanimeta_view_user && ! $fanimeta_is_self ) {
									printf( esc_html__( '%s 的主页', 'fanimeta' ), esc_html( $fanimeta_view_user->display_name ) );
								} else {
									esc_html_e( '个人主页', 'fanimeta' );
								}
								?>
							</h1>
						</header>

						<?php if ( $fanimeta_error ) : ?>
							<div class="submit-message submit-error"><?php echo esc_html( $fanimeta_error ); ?></div>
						<?php endif; ?>

						<?php if ( $fanimeta_success ) : ?>
							<div class="submit-message submit-success"><?php esc_html_e( '昵称已更新。', 'fanimeta' ); ?></div>
						<?php endif; ?>

						<?php if ( $fanimeta_avatar_success ) : ?>
							<div class="submit-message submit-success"><?php esc_html_e( '头像已更新。', 'fanimeta' ); ?></div>
						<?php endif; ?>

						<?php if ( 'pending' === $fanimeta_submitted ) : ?>
							<div class="submit-message submit-success"><?php esc_html_e( '投稿已提交，正在等待站长审核。可在下方「我的投稿」中查看进度。', 'fanimeta' ); ?></div>
						<?php elseif ( 'updated' === $fanimeta_submitted ) : ?>
							<div class="submit-message submit-success"><?php esc_html_e( '修改已提交，将重新进入审核队列。', 'fanimeta' ); ?></div>
						<?php endif; ?>

						<?php if ( ! $fanimeta_view_user ) : ?>

							<div class="submit-message">
								<p><?php esc_html_e( '未找到该用户，请检查 UID 或昵称是否正确。', 'fanimeta' ); ?></p>
								<?php if ( ! is_user_logged_in() ) : ?>
									<p>
									<a class="btn" data-auth-open="login" href="<?php echo esc_url( fanimeta_login_url( get_permalink() ) ); ?>"><?php esc_html_e( '登录', 'fanimeta' ); ?></a>
									<?php if ( get_option( 'users_can_register' ) ) : ?>
										<a class="btn btn-outline" href="<?php echo esc_url( fanimeta_register_url() ); ?>"><?php esc_html_e( '注册', 'fanimeta' ); ?></a>
									<?php endif; ?>
									</p>
								<?php endif; ?>
							</div>

						<?php else : ?>

							<!-- 用户信息 -->
							<div class="profile-header">
								<div class="profile-avatar">
									<?php echo get_avatar( $fanimeta_view_user->ID, 96 ); ?>
									<?php echo fanimeta_avatar_verify_html( $fanimeta_view_user->ID ); // 认证角标叠头像右下 ?>
								</div>
								<div class="profile-meta">
									<h2 class="profile-nickname"><?php echo esc_html( $fanimeta_view_user->display_name ); ?><?php fanimeta_owner_badge( $fanimeta_view_user->ID ); ?></h2>
									<p class="profile-uid"><?php esc_html_e( 'UID：', 'fanimeta' ); ?><strong><?php echo esc_html( $fanimeta_view_user->ID ); ?></strong><?php if ( fanimeta_is_owner( $fanimeta_view_user->ID ) ) : ?> <span class="profile-uid-owner"><?php esc_html_e( '（站长账号）', 'fanimeta' ); ?></span><?php endif; ?></p>
									<p class="profile-login"><?php echo esc_html( sprintf( __( '账号：%s', 'fanimeta' ), $fanimeta_view_user->user_login ) ); ?></p>
									<?php if ( fanimeta_is_owner( $fanimeta_view_user->ID ) ) : ?>
										<p class="profile-owner-link">
											<a class="profile-owner-btn" href="<?php echo esc_url( FANIMETA_OWNER_BILIBILI_URL ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( FANIMETA_OWNER_NAME . '的B站主页' ); ?>">
												<svg class="profile-owner-btn-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="#FB7299" d="M17.813 4.653h.854c1.51.054 2.769.578 3.773 1.574 1.004.995 1.524 2.249 1.56 3.76v7.36c-.036 1.51-.556 2.769-1.56 3.773s-2.262 1.524-3.773 1.56H5.333c-1.51-.036-2.769-.556-3.773-1.56S.036 18.858 0 17.347v-7.36c.036-1.511.556-2.765 1.56-3.76 1.004-.996 2.262-1.52 3.773-1.574h.774l-1.174-1.12a1.234 1.234 0 0 1-.373-.906c0-.356.124-.658.373-.907l.027-.027c.267-.249.573-.373.92-.373.347 0 .653.124.92.373L9.653 4.44c.071.071.134.142.187.213h4.267a.836.836 0 0 1 .16-.213l2.853-2.747c.267-.249.573-.373.92-.373.347 0 .662.151.929.4.267.249.391.551.391.907 0 .355-.124.657-.373.906zM5.333 7.24c-.746.018-1.373.276-1.88.773-.506.498-.769 1.13-.786 1.894v7.52c.017.764.28 1.395.786 1.893.507.498 1.134.756 1.88.773h13.334c.746-.017 1.373-.275 1.88-.773.506-.498.769-1.129.786-1.893v-7.52c-.017-.765-.28-1.396-.786-1.894-.507-.497-1.134-.755-1.88-.773zM8 11.107c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373zm8 0c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373z"/></svg>
												<span><?php esc_html_e( 'B站主页', 'fanimeta' ); ?></span>
											</a>
										</p>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( $fanimeta_is_self ) : ?>

								<!-- 头像上传（仅自己） -->
								<form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="profile-form profile-avatar-form" enctype="multipart/form-data">
									<?php wp_nonce_field( 'fanimeta_update_avatar' ); ?>
									<p class="submit-field">
										<label for="avatar"><?php esc_html_e( '自定义头像', 'fanimeta' ); ?></label>
										<input type="file" id="avatar" name="avatar" accept="image/*">
										<span class="hint"><?php esc_html_e( '支持 jpg / png / gif / webp，上传后自动替换当前头像。', 'fanimeta' ); ?></span>
									</p>
									<p class="submit-actions">
										<button type="submit" name="fanimeta_update_avatar" value="1" class="btn"><?php esc_html_e( '上传头像', 'fanimeta' ); ?></button>
									</p>
								</form>

								<!-- 昵称编辑（仅自己） -->
								<form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="profile-form">
									<?php wp_nonce_field( 'fanimeta_update_nickname' ); ?>
									<p class="submit-field">
										<label for="nickname"><?php esc_html_e( '自定义昵称', 'fanimeta' ); ?></label>
										<input type="text" id="nickname" name="nickname" value="<?php echo esc_attr( $fanimeta_view_user->display_name ); ?>" maxlength="20" required>
										<span class="hint"><?php esc_html_e( '只能包含中文、字母、数字或下划线，不能包含符号。', 'fanimeta' ); ?></span>
									</p>
									<p class="submit-actions">
										<button type="submit" name="fanimeta_update_nickname" value="1" class="btn"><?php esc_html_e( '保存昵称', 'fanimeta' ); ?></button>
									</p>
								</form>

							<?php endif; ?>

							<!-- 投稿中心：查看自己时列出全部状态，查看他人时只列已发布 -->
							<?php fanimeta_render_submission_center( $fanimeta_view_user->ID, $fanimeta_is_self ); ?>

						<?php endif; ?>

					</article>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
