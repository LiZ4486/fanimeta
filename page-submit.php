<?php
/**
 * 前端投稿页模板
 *
 * Template Name: 投稿
 *
 * 已登录用户可直接在本页发布文章，无需进入后台。
 *
 * 两种模式：
 * - 新建：直接访问本页
 * - 编辑：带 ?edit=<ID> 访问，仅限「本人 + 未发布」的文章（用于被驳回后改稿重投）
 *
 * 提交后的去向按文章状态分流：
 * - 管理员直接发布 → 跳文章页
 * - 普通用户 / 审核中 → 跳「个人主页 · 我的投稿」并提示，避免误以为已上线
 *
 * @package Fanimeta
 */

$fanimeta_error     = '';
$fanimeta_editing   = 0;
$fanimeta_edit_post = null;

// ── 0. 编辑模式：?edit=ID（必须在任何输出之前） ──
if ( isset( $_GET['edit'] ) && ctype_digit( (string) $_GET['edit'] ) ) {
	$fanimeta_editing = (int) $_GET['edit'];

	if ( is_user_logged_in() ) {
		$fanimeta_candidate = get_post( $fanimeta_editing );

		// 只允许编辑「自己的、未发布」的文章：
		// - 他人的文章不可编辑（防止越权改别人的稿）
		// - 已发布的文章不给前台编辑入口（改已上线内容应当走后台，避免审核被绕过）
		if (
			! $fanimeta_candidate
			|| (int) $fanimeta_candidate->post_author !== (int) get_current_user_id()
			|| in_array( $fanimeta_candidate->post_status, array( 'publish', 'trash' ), true )
		) {
			$fanimeta_editing = 0;
			$fanimeta_error   = __( '无法编辑该文章（可能不存在、不属于你，或已经发布）。', 'fanimeta' );
		} else {
			$fanimeta_edit_post = $fanimeta_candidate;
		}
	}
}

// ── 1. 处理投稿提交（必须在任何 HTML 输出之前） ──
if ( isset( $_POST['fanimeta_submit_post'] ) ) {
	if ( ! is_user_logged_in() ) {
		$fanimeta_error = __( '请先登录后再投稿。', 'fanimeta' );
	} elseif ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fanimeta_submit_post' ) ) {
		$fanimeta_error = __( '安全校验失败，请刷新页面后重试。', 'fanimeta' );
	} else {
		$fanimeta_title      = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$fanimeta_content    = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
		$fanimeta_categories = isset( $_POST['post_category'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['post_category'] ) ) : array();
		$fanimeta_tags       = isset( $_POST['post_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['post_tags'] ) ) : '';
		$fanimeta_post_id    = isset( $_POST['fanimeta_post_id'] ) ? (int) $_POST['fanimeta_post_id'] : 0;

		// 投稿状态：只有真正具备后台管理权限的账号（administrator）才允许直接发布。
		// 其余一律进审核队列。
		$fanimeta_status = current_user_can( 'manage_options' ) ? 'publish' : 'pending';

		if ( '' === $fanimeta_title ) {
			$fanimeta_error = __( '请填写文章标题。', 'fanimeta' );
		} elseif ( '' === trim( wp_strip_all_tags( $fanimeta_content ) ) ) {
			$fanimeta_error = __( '请填写文章内容。', 'fanimeta' );
		} elseif ( is_wp_error( $fanimeta_submit_limit = fanimeta_rate_limit( 'submit_post', FANIMETA_SUBMIT_MAX_PER_HOUR, HOUR_IN_SECONDS ) ) ) {
			// 投稿频次限制：新用户默认是投稿者（稿件进审核队列），
			// 但没有配额的话仍可批量灌稿，把审核队列和磁盘撑爆。
			$fanimeta_error = $fanimeta_submit_limit->get_error_message();
		} elseif ( $fanimeta_post_id ) {
			// ── 更新已有草稿（被驳回后改稿重投） ──
			$fanimeta_target = get_post( $fanimeta_post_id );

			if (
				! $fanimeta_target
				|| (int) $fanimeta_target->post_author !== (int) get_current_user_id()
				|| in_array( $fanimeta_target->post_status, array( 'publish', 'trash' ), true )
			) {
				$fanimeta_error = __( '无法更新该文章。', 'fanimeta' );
			} else {
				$fanimeta_result = wp_update_post(
					array(
						'ID'           => $fanimeta_post_id,
						'post_title'   => $fanimeta_title,
						'post_content' => $fanimeta_content,
						'post_status'  => $fanimeta_status,
					),
					true
				);

				if ( is_wp_error( $fanimeta_result ) ) {
					$fanimeta_error = $fanimeta_result->get_error_message();
				} else {
					wp_set_post_categories( $fanimeta_post_id, $fanimeta_categories );
					wp_set_post_tags( $fanimeta_post_id, $fanimeta_tags );

					// 改稿重投后清掉驳回标记，否则作者仍会看到「未通过」。
					delete_post_meta( $fanimeta_post_id, FANIMETA_META_REJECT_REASON );
					delete_post_meta( $fanimeta_post_id, FANIMETA_META_REJECTED_AT );

					fanimeta_maybe_replace_thumbnail( $fanimeta_post_id );

					wp_safe_redirect(
						add_query_arg(
							'fanimeta_submitted',
							'updated',
							fanimeta_get_profile_page_url()
						)
					);
					exit;
				}
			}
		} else {
			// ── 新建投稿 ──
			$fanimeta_new_id = wp_insert_post(
				array(
					'post_title'    => $fanimeta_title,
					'post_content'  => $fanimeta_content,
					'post_status'   => $fanimeta_status,
					'post_type'     => 'post',
					'post_category' => $fanimeta_categories,
					'tags_input'    => $fanimeta_tags,
				),
				true
			);

			if ( is_wp_error( $fanimeta_new_id ) ) {
				$fanimeta_error = $fanimeta_new_id->get_error_message();
			} else {
				fanimeta_maybe_replace_thumbnail( $fanimeta_new_id );

				// 直接发布（管理员）→ 文章页；进审核（普通用户）→ 投稿中心
				if ( 'publish' === $fanimeta_status ) {
					wp_safe_redirect( get_permalink( $fanimeta_new_id ) );
				} else {
					wp_safe_redirect( add_query_arg( 'fanimeta_submitted', 'pending', fanimeta_get_profile_page_url() ) );
				}
				exit;
			}
		}
	}

	// 校验失败时，把用户填的内容留在编辑态（$fanimeta_edit_post 为空则由下方 $_POST 回填）
}

// ── 2. 准备回填数据 ──
$fanimeta_is_edit = ( $fanimeta_edit_post instanceof WP_Post );

$fanimeta_val_title = '';
$fanimeta_val_tags  = '';
$fanimeta_val_body  = '';
$fanimeta_val_cats  = array();

if ( isset( $_POST['fanimeta_submit_post'] ) && $fanimeta_error ) {
	// 提交失败：优先用用户刚填的内容回填，避免白写
	$fanimeta_val_title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
	$fanimeta_val_tags  = isset( $_POST['post_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['post_tags'] ) ) : '';
	$fanimeta_val_body  = isset( $_POST['post_content'] ) ? wp_unslash( $_POST['post_content'] ) : '';
	$fanimeta_val_cats  = isset( $_POST['post_category'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['post_category'] ) ) : array();
} elseif ( $fanimeta_is_edit ) {
	$fanimeta_val_title = $fanimeta_edit_post->post_title;
	$fanimeta_val_body  = $fanimeta_edit_post->post_content;
	$fanimeta_val_cats  = wp_get_post_categories( $fanimeta_edit_post->ID );
	$fanimeta_val_tags  = implode( ',', wp_get_post_tags( $fanimeta_edit_post->ID, array( 'fields' => 'names' ) ) );
}

$fanimeta_draft_key = $fanimeta_is_edit ? (string) $fanimeta_edit_post->ID : 'new';

get_header();
?>

<main class="main" id="main" role="main">
	<div class="main-inner">
		<div class="main-layout">

			<?php get_sidebar(); ?>

			<div class="content-wrap">
				<div class="content">

					<article class="post-block submit-page">

						<header class="post-header">
							<h1 class="post-title"><?php echo $fanimeta_is_edit ? esc_html__( '修改稿件', 'fanimeta' ) : esc_html__( '投稿', 'fanimeta' ); ?></h1>
							<?php if ( $fanimeta_is_edit ) : ?>
								<p class="submit-subtitle"><?php esc_html_e( '修改后会重新进入审核队列。', 'fanimeta' ); ?></p>
							<?php else : ?>
								<p class="submit-subtitle"><?php esc_html_e( '稿件将先进入审核队列，站长通过后自动上线。', 'fanimeta' ); ?></p>
							<?php endif; ?>
						</header>

						<?php if ( $fanimeta_error ) : ?>
							<div class="submit-message submit-error"><?php echo esc_html( $fanimeta_error ); ?></div>
						<?php endif; ?>

						<?php if ( is_user_logged_in() ) : ?>

							<!-- 草稿恢复提示（有本地草稿时才显示，由脚本控制） -->
							<div class="draft-restore" id="fanimeta-draft-restore" hidden>
								<span><?php esc_html_e( '检测到上次未提交的草稿。', 'fanimeta' ); ?></span>
								<button type="button" class="btn btn-outline" id="fanimeta-draft-resume"><?php esc_html_e( '恢复', 'fanimeta' ); ?></button>
								<button type="button" class="btn btn-outline" id="fanimeta-draft-discard"><?php esc_html_e( '忽略', 'fanimeta' ); ?></button>
							</div>

							<form method="post" action="<?php echo esc_url( $fanimeta_is_edit ? add_query_arg( 'edit', $fanimeta_edit_post->ID, get_permalink() ) : get_permalink() ); ?>" enctype="multipart/form-data" class="submit-form" id="fanimeta-submit-form" data-draft-key="<?php echo esc_attr( $fanimeta_draft_key ); ?>" data-user="<?php echo (int) get_current_user_id(); ?>">
								<?php wp_nonce_field( 'fanimeta_submit_post' ); ?>
								<?php if ( $fanimeta_is_edit ) : ?>
									<input type="hidden" name="fanimeta_post_id" value="<?php echo (int) $fanimeta_edit_post->ID; ?>">
								<?php endif; ?>

								<p class="submit-field">
									<label for="post_title"><?php esc_html_e( '标题', 'fanimeta' ); ?> <span class="required">*</span></label>
									<input type="text" id="post_title" name="post_title" value="<?php echo esc_attr( $fanimeta_val_title ); ?>" maxlength="120" required>
								</p>

								<p class="submit-field">
									<label><?php esc_html_e( '分类', 'fanimeta' ); ?></label>
									<span class="submit-categories">
										<?php $fanimeta_cats = get_categories( array( 'hide_empty' => false ) ); ?>
										<?php if ( $fanimeta_cats ) : ?>
											<?php foreach ( $fanimeta_cats as $fanimeta_cat ) : ?>
												<label class="cat-checkbox">
													<input type="checkbox" name="post_category[]" value="<?php echo esc_attr( $fanimeta_cat->term_id ); ?>" <?php checked( in_array( (int) $fanimeta_cat->term_id, array_map( 'intval', $fanimeta_val_cats ), true ) ); ?>>
													<?php echo esc_html( $fanimeta_cat->name ); ?>
												</label>
											<?php endforeach; ?>
										<?php else : ?>
											<span class="hint"><?php esc_html_e( '暂无分类，可在后台创建。', 'fanimeta' ); ?></span>
										<?php endif; ?>
									</span>
								</p>

								<p class="submit-field">
									<label for="post_tags"><?php esc_html_e( '标签（用逗号分隔）', 'fanimeta' ); ?></label>
									<input type="text" id="post_tags" name="post_tags" value="<?php echo esc_attr( $fanimeta_val_tags ); ?>">
								</p>

								<p class="submit-field">
									<label for="post_thumbnail"><?php esc_html_e( '特色图片（可选）', 'fanimeta' ); ?></label>
									<input type="file" id="post_thumbnail" name="post_thumbnail" accept="image/*">
									<span class="submit-thumb-preview" id="fanimeta-thumb-preview" hidden><img alt="" id="fanimeta-thumb-img"></span>
									<?php if ( $fanimeta_is_edit && has_post_thumbnail( $fanimeta_edit_post->ID ) ) : ?>
										<span class="hint"><?php esc_html_e( '当前已有封面，重新选择将替换。', 'fanimeta' ); ?></span>
									<?php endif; ?>
								</p>

								<p class="submit-field">
									<label for="post_content"><?php esc_html_e( '内容', 'fanimeta' ); ?> <span class="required">*</span></label>
									<textarea id="post_content" name="post_content" rows="16" required><?php echo esc_textarea( $fanimeta_val_body ); ?></textarea>
									<span class="submit-counter" id="fanimeta-counter">
										<span id="fanimeta-char-count">0</span> <?php esc_html_e( '字', 'fanimeta' ); ?>
										· <?php esc_html_e( '约', 'fanimeta' ); ?>
										<span id="fanimeta-read-min">1</span> <?php esc_html_e( '分钟阅读', 'fanimeta' ); ?>
									</span>
								</p>

								<p class="submit-actions">
									<button type="submit" name="fanimeta_submit_post" value="1" class="btn" id="fanimeta-submit-btn">
										<?php echo $fanimeta_is_edit ? esc_html__( '提交修改', 'fanimeta' ) : esc_html__( '提交稿件', 'fanimeta' ); ?>
									</button>
									<span class="submit-autosave-hint" id="fanimeta-autosave-hint" aria-live="polite"></span>
								</p>
							</form>

						<?php else : ?>

							<div class="submit-message">
								<p><?php esc_html_e( '登录后即可发布文章。', 'fanimeta' ); ?></p>
								<p>
								<a class="btn" data-auth-open="login" href="<?php echo esc_url( fanimeta_login_url( get_permalink() ) ); ?>"><?php esc_html_e( '登录', 'fanimeta' ); ?></a>
								<?php if ( get_option( 'users_can_register' ) ) : ?>
									<a class="btn btn-outline" href="<?php echo esc_url( fanimeta_register_url() ); ?>"><?php esc_html_e( '注册', 'fanimeta' ); ?></a>
								<?php endif; ?>
								</p>
							</div>

						<?php endif; ?>

					</article>

				</div>
			</div>

		</div>
	</div>
</main>

<?php if ( is_user_logged_in() ) : ?>
<script>
/* 投稿页交互：草稿自动保存 / 字数统计 / 封面预览 / 防重复提交 */
(function () {
	'use strict';

	var form = document.getElementById('fanimeta-submit-form');
	if (!form) { return; }

	var titleEl = document.getElementById('post_title');
	var tagsEl  = document.getElementById('post_tags');
	var bodyEl  = document.getElementById('post_content');
	var draftKey = 'fanimeta_draft_' + (form.dataset.user || '0') + '_' + (form.dataset.draftKey || 'new');

	/* ---------- 字数与阅读时长 ---------- */
	var charCount = document.getElementById('fanimeta-char-count');
	var readMin   = document.getElementById('fanimeta-read-min');

	function updateCounter() {
		var text = (bodyEl.value || '').replace(/\s+/g, '');
		var n = text.length;
		if (charCount) { charCount.textContent = n; }
		if (readMin) { readMin.textContent = Math.max(1, Math.ceil(n / 400)); }
	}

	/* ---------- 草稿自动保存 ---------- */
	var hint = document.getElementById('fanimeta-autosave-hint');
	var saveTimer = null;

	function collectCats() {
		var out = [];
		var boxes = form.querySelectorAll('input[name="post_category[]"]');
		for (var i = 0; i < boxes.length; i++) {
			if (boxes[i].checked) { out.push(boxes[i].value); }
		}
		return out;
	}

	function snapshot() {
		return {
			title: titleEl ? titleEl.value : '',
			tags: tagsEl ? tagsEl.value : '',
			body: bodyEl ? bodyEl.value : '',
			cats: collectCats(),
			ts: Date.now()
		};
	}

	function writeDraft() {
		try {
			localStorage.setItem(draftKey, JSON.stringify(snapshot()));
			if (hint) {
				hint.textContent = '草稿已自动保存';
				setTimeout(function () { if (hint.textContent === '草稿已自动保存') { hint.textContent = ''; } }, 2000);
			}
		} catch (e) {}
	}

	function scheduleSave() {
		if (saveTimer) { clearTimeout(saveTimer); }
		saveTimer = setTimeout(writeDraft, 1500);
	}

	// 有实质内容才保存，避免把空表单也写进去
	function hasContent() {
		return (titleEl && titleEl.value.trim()) || (bodyEl && bodyEl.value.trim());
	}

	['input', 'change'].forEach(function (ev) {
		form.addEventListener(ev, function () {
			updateCounter();
			if (hasContent()) { scheduleSave(); }
		});
	});

	/* ---------- 恢复草稿 ---------- */
	var restoreBox = document.getElementById('fanimeta-draft-restore');
	var saved = null;

	try {
		var raw = localStorage.getItem(draftKey);
		if (raw) { saved = JSON.parse(raw); }
	} catch (e) { saved = null; }

	// 只有本地草稿比已加载内容更新、且确实有内容时才提示
	if (saved && saved.body && saved.body.trim()) {
		var loadedLen = bodyEl ? bodyEl.value.trim().length : 0;
		var savedLen  = saved.body.trim().length;
		// 编辑模式：草稿与正文完全一致则无需提示
		var identical = (bodyEl && saved.body === bodyEl.value) && (titleEl && saved.title === titleEl.value);
		if (!identical && savedLen > loadedLen) {
			if (restoreBox) { restoreBox.hidden = false; }
		}
	}

	var resumeBtn = document.getElementById('fanimeta-draft-resume');
	if (resumeBtn) {
		resumeBtn.addEventListener('click', function () {
			if (!saved) { return; }
			if (titleEl) { titleEl.value = saved.title || ''; }
			if (tagsEl) { tagsEl.value = saved.tags || ''; }
			if (bodyEl) { bodyEl.value = saved.body || ''; }
			var boxes = form.querySelectorAll('input[name="post_category[]"]');
			for (var i = 0; i < boxes.length; i++) {
				boxes[i].checked = (saved.cats || []).indexOf(boxes[i].value) !== -1;
			}
			updateCounter();
			if (restoreBox) { restoreBox.hidden = true; }
		});
	}

	var discardBtn = document.getElementById('fanimeta-draft-discard');
	if (discardBtn) {
		discardBtn.addEventListener('click', function () {
			try { localStorage.removeItem(draftKey); } catch (e) {}
			if (restoreBox) { restoreBox.hidden = true; }
		});
	}

	/* ---------- 封面预览 ---------- */
	var thumbInput = document.getElementById('post_thumbnail');
	var thumbBox   = document.getElementById('fanimeta-thumb-preview');
	var thumbImg   = document.getElementById('fanimeta-thumb-img');
	if (thumbInput && thumbBox && thumbImg) {
		thumbInput.addEventListener('change', function () {
			var file = thumbInput.files && thumbInput.files[0];
			if (!file) { thumbBox.hidden = true; return; }
			var reader = new FileReader();
			reader.onload = function (e) {
				thumbImg.src = e.target.result;
				thumbBox.hidden = false;
			};
			reader.readAsDataURL(file);
		});
	}

	/* ---------- 提交：清草稿 + 防重复 ---------- */
	form.addEventListener('submit', function () {
		try { localStorage.removeItem(draftKey); } catch (e) {}
		var btn = document.getElementById('fanimeta-submit-btn');
		if (btn) {
			btn.disabled = true;
			btn.textContent = '提交中…';
		}
	});

	updateCounter();
})();
</script>
<?php endif; ?>

<?php
get_footer();
