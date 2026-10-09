<?php
/**
 * 单篇文章页
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

						<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-block' ); ?> itemscope itemtype="https://schema.org/Article">

							<header class="post-header">
								<?php the_title( '<h1 class="post-title" itemprop="headline">', '</h1>' ); ?>

								<div class="post-meta">
									<span class="post-time">
										<i class="fa fa-calendar" aria-hidden="true"></i>
										<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" itemprop="datePublished"><?php echo esc_html( get_the_date() ); ?></time>
									</span>

									<?php fanimeta_post_author_badge(); ?>

									<?php if ( has_category() ) : ?>
										<span class="post-category">
											<i class="fa fa-folder" aria-hidden="true"></i>
											<?php the_category( ' ' ); ?>
										</span>
									<?php endif; ?>

									<span class="post-reading-time">
										<i class="fa fa-clock-o" aria-hidden="true"></i>
										<?php printf( esc_html__( '%d 分钟阅读', 'fanimeta' ), (int) fanimeta_reading_time() ); ?>
									</span>
								</div>
							</header>

							<?php if ( has_post_thumbnail() ) : ?>
								<div class="post-featured">
									<?php the_post_thumbnail( 'fanimeta-featured', array( 'itemprop' => 'image' ) ); ?>
								</div>
							<?php endif; ?>

							<div class="post-body" itemprop="articleBody">
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

							<footer class="post-footer">
								<?php if ( has_tag() ) : ?>
									<div class="post-tags">
										<i class="fa fa-tags" aria-hidden="true"></i>
										<?php the_tags( '', ' ', '' ); ?>
									</div>
								<?php endif; ?>
							</footer>

						</article>

						<?php
						the_post_navigation(
							array(
								'prev_text' => '&laquo; %title',
								'next_text' => '%title &raquo;',
							)
						);
						?>

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
