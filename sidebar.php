<?php
/**
 * 侧边栏
 *
 * @package Fanimeta
 */

// 侧边栏完全空着才不输出：有 widget、有站点描述、或更新日志有数据，都算有内容。
$fanimeta_has_changelog = function_exists( 'fanimeta_changelog_data' ) && fanimeta_changelog_data();

if ( ! is_active_sidebar( 'sidebar-1' ) && ! get_bloginfo( 'description' ) && ! $fanimeta_has_changelog ) {
	return;
}
?>

<aside class="sidebar" itemscope itemtype="https://schema.org/WPSideBar">
	<div class="sidebar-inner">

		<div class="site-overview-wrap">

			<?php
			// 作者头像：使用主题内置头像图片
			?>
			<div class="site-author">
				<img class="site-author-image" itemprop="image" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/avatar.webp' ); ?>">
				<p class="site-author-name"><?php bloginfo( 'name' ); ?></p>
				<?php if ( get_bloginfo( 'description' ) ) : ?>
					<p class="site-description"><?php bloginfo( 'description' ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( has_nav_menu( 'social' ) ) : ?>
				<div class="links-of-author">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'social',
							'menu_class'     => 'links-of-author-list',
							'container'      => false,
							'fallback_cb'    => false,
							'depth'          => 1,
						)
					);
					?>
				</div>
			<?php endif; ?>

		</div>

		<?php if ( is_active_sidebar( 'sidebar-1' ) ) : ?>
			<div class="sidebar-widgets">
				<?php dynamic_sidebar( 'sidebar-1' ); ?>
			</div>
		<?php endif; ?>

		<?php
		/**
		 * 更新日志卡片：刻意放在侧边栏最底部，样式也收得比较淡，不抢眼。
		 * 数据维护在 inc/changelog.php 的 fanimeta_changelog_data()。
		 */
		if ( function_exists( 'fanimeta_changelog_sidebar' ) ) {
			fanimeta_changelog_sidebar( 5 );
		}
		?>

	</div>
</aside>
