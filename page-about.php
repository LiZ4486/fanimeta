<?php
/**
 * 关于页模板
 *
 * Template Name: 关于页
 *
 * 当页面固定链接（slug）为 "about" 时自动使用本模板。
 *
 * @package Fanimeta
 */

get_header();
?>

<main class="main" id="main" role="main">
	<div class="main-inner">
		<div class="main-layout">

			<?php get_sidebar(); ?>

			<div class="content-wrap">
				<div class="content">

					<?php while ( have_posts() ) : ?>
						<?php the_post(); ?>

						<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-block about-page' ); ?>>

							<header class="about-header">
								<img class="about-avatar" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/avatar.webp' ); ?>">
								<h1 class="post-title about-name"><?php bloginfo( 'name' ); ?></h1>
								<?php if ( get_bloginfo( 'description' ) ) : ?>
									<p class="about-description"><?php bloginfo( 'description' ); ?></p>
								<?php endif; ?>
							</header>

							<div class="about-state">
								<span class="site-state-item"><span class="site-state-item-count"><?php echo esc_html( fanimeta_post_count() ); ?></span><span class="site-state-item-name"><?php esc_html_e( '文章', 'fanimeta' ); ?></span></span>
								<span class="site-state-item"><span class="site-state-item-count"><?php echo esc_html( fanimeta_category_count() ); ?></span><span class="site-state-item-name"><?php esc_html_e( '分类', 'fanimeta' ); ?></span></span>
								<span class="site-state-item"><span class="site-state-item-count"><?php echo esc_html( fanimeta_tag_count() ); ?></span><span class="site-state-item-name"><?php esc_html_e( '标签', 'fanimeta' ); ?></span></span>
							</div>

							<div class="post-body about-body">
								<?php
								the_content();

								wp_link_pages(
									array(
										'before' => '<div class="page-links">' . esc_html__( '分页：', 'fanimeta' ),
										'after'  => '</div>',
									)
								);
								?>
							</div>

							<?php if ( has_nav_menu( 'social' ) ) : ?>
								<div class="about-social">
									<h3 class="widget-title"><?php esc_html_e( '找到我', 'fanimeta' ); ?></h3>
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
								</div>
							<?php endif; ?>

						</article>

					<?php endwhile; ?>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
