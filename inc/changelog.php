<?php
/**
 * 更新日志（Changelog）
 *
 * 数据的唯一来源是下方的 fanimeta_changelog_data()，按版本从新到旧排列。
 * 前台两处消费它：
 *   1) 首页左侧栏卡片 —— fanimeta_changelog_sidebar()，见 sidebar.php
 *   2) 独立页面 /changelog/ —— fanimeta_changelog_render()，见 page-changelog.php
 *
 * 页面不需要手工创建：版本号变化后首次访问时，fanimeta_maybe_create_changelog_page()
 * 会自动建好页面并绑定模板；若页面被误删，下次版本升级会重新建回来。
 *
 * @package Fanimeta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 更新日志数据。
 *
 * 新增一版时，在最前面插一条即可（数组顺序 = 展示顺序，从新到旧）：
 *
 *     array(
 *         'version' => '1.14.0',
 *         'date'    => '2026-10-20',
 *         'type'    => 'feature',   // feature 新增 / fix 修复 / polish 优化
 *         'title'   => '一句话说清这一版做了什么',
 *         'items'   => array(
 *             '细项一',
 *             '细项二',
 *         ),
 *     ),
 *
 * 说明：'date' 用 YYYY-MM-DD，侧边栏会自动缩写成 MM-DD。
 *
 * @return array
 */
function fanimeta_changelog_data() {
	$fanimeta_logs = array(
		array(
			'version' => '1.13.1',
			'date'    => '2026-10-09',
			'type'    => 'feature',
			'title'   => '新增「更新日志」独立页面与侧边栏动态',
			'items'   => array(
				'新增独立页面 /changelog/，按时间倒序展示站点的每一版改动',
				'侧边栏底部新增「更新日志」小块，展示最近 5 个版本，点条目直达对应记录',
				'日志数据写在主题内（inc/changelog.php），改一行即可发布一条',
			),
		),
		array(
			'version' => '1.12.0',
			'date'    => '2026-10-09',
			'type'    => 'feature',
			'title'   => '运营增强：投稿中心、投稿页升级、内容审核、站点概览',
			'items'   => array(
				'新增「我的投稿中心」，作者可自助查看稿件审核状态与驳回原因',
				'投稿页升级：草稿自动保存、封面图上传、字数与分类校验',
				'新增内容审核工作流：一键通过 / 驳回 / 恢复，附驳回理由',
				'新增站点概览看板：发布趋势、待办数字、站点健康一览',
			),
		),
		array(
			'version' => '1.11.0',
			'date'    => '2026-10-09',
			'type'    => 'fix',
			'title'   => '安全加固第二轮（21 项）',
			'items'   => array(
				'修复中危 5 项、低危 13 项与全部信息级提示',
				'作者归档页统一走 404，避免被枚举出全部作者',
				'收紧注册与找回流程，可疑账号降权为投稿者',
				'补齐 nonce 校验与能力检查，加固上传与查询拼装',
			),
		),
		array(
			'version' => '1.10.0',
			'date'    => '2026-10-08',
			'type'    => 'fix',
			'title'   => '安全加固第一轮（13 项）',
			'items'   => array(
				'修复 3 项高危：权限校验缺失、越权读写、上传校验绕过',
				'修复 9 项中危与 1 项低危，统一过滤与转义',
				'开启强制 HTTPS 与后台访问限制',
			),
		),
		array(
			'version' => '1.9.0',
			'date'    => '2026-09-30',
			'type'    => 'feature',
			'title'   => '主题基线：自建账号体系与用户中心',
			'items'   => array(
				'自建登录 / 注册 / 找回密码，含邮箱验证码',
				'用户中心：头像、封面、昵称与个人主页',
				'投稿入口与内容发布流程初版',
			),
		),
	);

	/**
	 * 允许子主题或插件覆盖更新日志数据。
	 *
	 * @param array $fanimeta_logs 版本记录数组。
	 */
	return apply_filters( 'fanimeta_changelog_data', $fanimeta_logs );
}

/**
 * 取最近 N 条版本记录。
 *
 * @param int $limit 条数。
 * @return array
 */
function fanimeta_changelog_recent( $limit = 5 ) {
	$fanimeta_logs = fanimeta_changelog_data();
	$limit         = (int) $limit;

	if ( $limit < 1 ) {
		return $fanimeta_logs;
	}

	return array_slice( $fanimeta_logs, 0, $limit );
}

/**
 * 版本类型 → 中文标签。
 *
 * @param string $type feature / fix / polish。
 * @return string
 */
function fanimeta_changelog_type_label( $type ) {
	$fanimeta_map = array(
		'feature' => __( '新增', 'fanimeta' ),
		'fix'     => __( '修复', 'fanimeta' ),
		'polish'  => __( '优化', 'fanimeta' ),
	);

	return isset( $fanimeta_map[ $type ] ) ? $fanimeta_map[ $type ] : __( '更新', 'fanimeta' );
}

/**
 * 更新日志页面链接。
 *
 * 用 static 缓存，避免侧边栏里每个条目都触发一次 get_page_by_path 查询。
 *
 * @return string
 */
function fanimeta_get_changelog_page_url() {
	static $fanimeta_url = null;

	if ( null !== $fanimeta_url ) {
		return $fanimeta_url;
	}

	$fanimeta_page = get_page_by_path( 'changelog' );
	$fanimeta_url  = $fanimeta_page ? get_permalink( $fanimeta_page ) : home_url( '/changelog/' );

	return $fanimeta_url;
}

/**
 * 带版本锚点的更新日志链接，点进去直接落在对应那条记录上。
 *
 * @param string $version 版本号，如 1.13.0。
 * @return string
 */
function fanimeta_changelog_version_url( $version ) {
	$fanimeta_anchor = sanitize_html_class( 'v' . str_replace( '.', '-', (string) $version ) );

	return fanimeta_get_changelog_page_url() . '#' . $fanimeta_anchor;
}

/**
 * 日期缩写：YYYY-MM-DD → MM-DD（侧边栏空间有限）。
 *
 * 直接用字符串截取而不是 strtotime，避免时区把日期挪掉一天。
 *
 * @param string $date 完整日期。
 * @return string
 */
function fanimeta_changelog_short_date( $date ) {
	$fanimeta_parts = explode( '-', (string) $date );

	if ( 3 === count( $fanimeta_parts ) ) {
		return $fanimeta_parts[1] . '-' . $fanimeta_parts[2];
	}

	return (string) $date;
}

/**
 * 侧边栏「更新日志」卡片。
 *
 * @param int $limit 展示条数，默认 5。
 */
function fanimeta_changelog_sidebar( $limit = 5 ) {
	$fanimeta_logs = fanimeta_changelog_recent( $limit );

	if ( empty( $fanimeta_logs ) ) {
		return;
	}
	?>
	<div class="changelog-widget">
		<h3 class="changelog-widget-title"><?php esc_html_e( '更新日志', 'fanimeta' ); ?></h3>

		<ul class="changelog-list">
			<?php foreach ( $fanimeta_logs as $fanimeta_log ) : ?>
				<?php
				// 顺序不能改：grid 按 DOM 顺序自动排布 ——
				// 版本号占左上、日期靠右对齐、标题整行换行。
				?>
				<li class="changelog-item">
					<span class="changelog-ver"><?php echo esc_html( $fanimeta_log['version'] ); ?></span>
					<time class="changelog-date" datetime="<?php echo esc_attr( $fanimeta_log['date'] ); ?>"><?php echo esc_html( fanimeta_changelog_short_date( $fanimeta_log['date'] ) ); ?></time>
					<a class="changelog-link" href="<?php echo esc_url( fanimeta_changelog_version_url( $fanimeta_log['version'] ) ); ?>"><?php echo esc_html( $fanimeta_log['title'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>

		<a class="changelog-more" href="<?php echo esc_url( fanimeta_get_changelog_page_url() ); ?>">
			<?php esc_html_e( '查看全部', 'fanimeta' ); ?> <span aria-hidden="true">&raquo;</span>
		</a>
	</div>
	<?php
}

/**
 * 独立页面上的完整时间线。
 */
function fanimeta_changelog_render() {
	$fanimeta_logs = fanimeta_changelog_data();

	if ( empty( $fanimeta_logs ) ) {
		echo '<p class="changelog-empty">' . esc_html__( '暂无更新记录。', 'fanimeta' ) . '</p>';
		return;
	}
	?>
	<div class="changelog-timeline">
		<?php foreach ( $fanimeta_logs as $fanimeta_log ) : ?>
			<section class="changelog-entry" id="<?php echo esc_attr( sanitize_html_class( 'v' . str_replace( '.', '-', $fanimeta_log['version'] ) ) ); ?>">

				<div class="changelog-entry-head">
					<span class="changelog-entry-ver"><?php echo esc_html( $fanimeta_log['version'] ); ?></span>
					<span class="changelog-tag <?php echo esc_attr( sanitize_html_class( 'changelog-tag-' . $fanimeta_log['type'] ) ); ?>"><?php echo esc_html( fanimeta_changelog_type_label( $fanimeta_log['type'] ) ); ?></span>
					<time class="changelog-entry-date" datetime="<?php echo esc_attr( $fanimeta_log['date'] ); ?>"><?php echo esc_html( $fanimeta_log['date'] ); ?></time>
				</div>

				<h2 class="changelog-entry-title"><?php echo esc_html( $fanimeta_log['title'] ); ?></h2>

				<?php if ( ! empty( $fanimeta_log['items'] ) ) : ?>
					<ul class="changelog-entry-list">
						<?php foreach ( $fanimeta_log['items'] as $fanimeta_item ) : ?>
							<li><?php echo esc_html( $fanimeta_item ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

			</section>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * 自动创建「更新日志」页面并绑定模板。
 *
 * 挂在 init 上，但只在 FANIMETA_VERSION 变化后跑一次：
 * 版本号没变时只是一次 autoload option 读取（零额外查询），不会给每个请求增加负担。
 *
 * 之所以不挂在 after_switch_theme：线上站点主题早已激活，那个钩子不会再触发。
 */
function fanimeta_maybe_create_changelog_page() {
	if ( FANIMETA_VERSION === get_option( 'fanimeta_pages_synced_version' ) ) {
		return;
	}

	$fanimeta_page = get_page_by_path( 'changelog' );

	if ( ! $fanimeta_page ) {
		$fanimeta_page_id = wp_insert_post(
			array(
				'post_title'   => __( '更新日志', 'fanimeta' ),
				'post_name'    => 'changelog',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			)
		);

		if ( $fanimeta_page_id && ! is_wp_error( $fanimeta_page_id ) ) {
			update_post_meta( $fanimeta_page_id, '_wp_page_template', 'page-changelog.php' );
		}
	} elseif ( 'page-changelog.php' !== get_post_meta( $fanimeta_page->ID, '_wp_page_template', true ) ) {
		// 页面在，但模板绑定被改过（比如后台手动切换过），纠正回来。
		update_post_meta( $fanimeta_page->ID, '_wp_page_template', 'page-changelog.php' );
	}

	update_option( 'fanimeta_pages_synced_version', FANIMETA_VERSION );
}
add_action( 'init', 'fanimeta_maybe_create_changelog_page' );
