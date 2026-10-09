<?php
/**
 * 404 页面
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

					<div class="error-404">
						<h1 class="error-title">404</h1>
						<p><?php esc_html_e( '抱歉，你访问的页面不存在或已被移除。', 'fanimeta' ); ?></p>

						<?php get_search_form(); ?>

						<p style="margin-top: 20px;">
							<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'fanimeta' ); ?></a>
						</p>
					</div>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
