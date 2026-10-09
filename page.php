<?php
/**
 * 独立页面模板
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

						<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-block' ); ?>>

							<header class="post-header">
								<?php the_title( '<h1 class="post-title">', '</h1>' ); ?>
							</header>

							<div class="post-body">
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

						</article>

						<?php
						if ( comments_open() || get_comments_number() ) {
							comments_template();
						}
						?>

					<?php endwhile; ?>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
