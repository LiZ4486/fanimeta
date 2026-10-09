<?php
/**
 * 更新日志页模板
 *
 * Template Name: 更新日志
 *
 * 固定链接（slug）为 "changelog" 时自动使用本模板。
 * 版本数据与渲染逻辑都在 inc/changelog.php，本文件只负责版式。
 *
 * 页面正文（后台编辑器里的内容）如果写了东西，会作为页首引言展示；
 * 留空则用一句默认引导语。
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

						<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-block changelog-page' ); ?>>

							<header class="post-header changelog-header">
								<h1 class="post-title"><?php the_title(); ?></h1>

								<?php if ( trim( (string) get_the_content() ) ) : ?>
									<div class="changelog-intro"><?php the_content(); ?></div>
								<?php else : ?>
									<p class="changelog-intro changelog-intro-default"><?php esc_html_e( '这里记录站点的每一次改动：新增了什么，修好了什么。', 'fanimeta' ); ?></p>
								<?php endif; ?>
							</header>

							<div class="post-body changelog-body">
								<?php
								if ( function_exists( 'fanimeta_changelog_render' ) ) {
									fanimeta_changelog_render();
								}
								?>
							</div>

						</article>

					<?php endwhile; ?>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
