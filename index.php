<?php
/**
 * 主模板：文章列表页
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
				<div class="content index posts-expand">

					<?php if ( have_posts() ) : ?>

						<?php while ( have_posts() ) : ?>
							<?php the_post(); ?>

							<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-block' ); ?> itemscope itemtype="https://schema.org/Article">

								<header class="post-header">
									<?php the_title( '<h2 class="post-title" itemprop="headline"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>

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

								<div class="post-body" itemprop="articleBody">
									<?php if ( has_post_thumbnail() ) : ?>
										<a class="post-thumbnail" href="<?php the_permalink(); ?>">
											<?php the_post_thumbnail( 'fanimeta-list-thumb', array( 'itemprop' => 'image' ) ); ?>
										</a>
									<?php endif; ?>

									<?php the_excerpt(); ?>

									<a class="more-link" href="<?php the_permalink(); ?>"><?php esc_html_e( '阅读全文 &raquo;', 'fanimeta' ); ?></a>
								</div>

							</article>

						<?php endwhile; ?>

						<div class="pagination">
							<?php
							the_posts_pagination(
								array(
									'mid_size'  => 2,
									'prev_text' => __( '&laquo; 上一页', 'fanimeta' ),
									'next_text' => __( '下一页 &raquo;', 'fanimeta' ),
								)
							);
							?>
						</div>

					<?php else : ?>

						<div class="post-block">
							<p><?php esc_html_e( '暂无内容，请先发布文章。', 'fanimeta' ); ?></p>
						</div>

					<?php endif; ?>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
