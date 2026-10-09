<?php
/**
 * 归档页（分类、标签、日期、作者等）
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

					<header class="archive-header">
						<?php
						the_archive_title( '<h1 class="archive-title">', '</h1>' );
						the_archive_description( '<div class="archive-description">', '</div>' );
						?>
					</header>

					<div class="archive-list">
						<?php if ( have_posts() ) : ?>

							<ul>
								<?php while ( have_posts() ) : ?>
									<?php the_post(); ?>
									<li>
										<span class="post-date"><?php echo esc_html( get_the_date() ); ?></span>
										<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
									</li>
								<?php endwhile; ?>
							</ul>

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
							<p><?php esc_html_e( '暂无内容。', 'fanimeta' ); ?></p>
						<?php endif; ?>
					</div>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
