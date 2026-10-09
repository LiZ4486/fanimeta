<?php
/**
 * 搜索结果页
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
						<h1 class="archive-title">
							<?php
							/* translators: %s: 搜索关键词 */
							printf( esc_html__( '搜索：%s', 'fanimeta' ), '<span>' . get_search_query() . '</span>' );
							?>
						</h1>
					</header>

					<?php
					// 统一搜索：关键词同时匹配用户（UID / 登录名 / 昵称 / 显示名）
					$fanimeta_search_kw   = get_search_query();
					$fanimeta_found_users = array();

					if ( '' !== $fanimeta_search_kw ) {
						// 精确匹配（纯数字按 UID，否则按登录名 / slug / 显示名 / 昵称）
						$fanimeta_primary_user = fanimeta_find_user( $fanimeta_search_kw );
						if ( $fanimeta_primary_user ) {
							$fanimeta_found_users[ $fanimeta_primary_user->ID ] = $fanimeta_primary_user;
						}

						// 模糊匹配：登录名 / 邮箱 / nicename / 显示名
						$fanimeta_more_users = get_users(
							array(
								'search'  => '*' . $fanimeta_search_kw . '*',
								'number'  => 10,
							)
						);
						foreach ( $fanimeta_more_users as $fanimeta_u ) {
							$fanimeta_found_users[ $fanimeta_u->ID ] = $fanimeta_u;
						}

						// 模糊匹配：昵称（user meta）
						$fanimeta_nick_users = get_users(
							array(
								'meta_key'   => 'nickname',
								'meta_value' => $fanimeta_search_kw,
								'number'     => 10,
							)
						);
						foreach ( $fanimeta_nick_users as $fanimeta_u ) {
							$fanimeta_found_users[ $fanimeta_u->ID ] = $fanimeta_u;
						}
					}
					?>

					<?php if ( ! empty( $fanimeta_found_users ) ) : ?>
						<section class="search-users">
							<h2 class="widget-title"><?php esc_html_e( '相关用户', 'fanimeta' ); ?></h2>
							<ul class="search-user-list">
								<?php foreach ( $fanimeta_found_users as $fanimeta_u ) : ?>
									<li class="search-user-item">
										<a class="search-user-link" href="<?php echo esc_url( fanimeta_profile_url( $fanimeta_u->ID ) ); ?>">
											<span class="author-avatar-wrap search-user-avatar">
												<?php echo get_avatar( $fanimeta_u->ID, 40 ); ?>
												<?php echo fanimeta_avatar_verify_html( $fanimeta_u->ID ); // 认证角标叠头像右下 ?>
											</span>
											<span class="search-user-name"><?php echo esc_html( $fanimeta_u->display_name ); ?></span>
											<?php fanimeta_owner_badge( $fanimeta_u->ID ); ?>
											<span class="search-user-uid"><?php echo esc_html( sprintf( __( 'UID：%d', 'fanimeta' ), $fanimeta_u->ID ) ); ?></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

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
							<p><?php empty( $fanimeta_found_users ) ? esc_html_e( '没有找到相关内容，请尝试其他关键词。', 'fanimeta' ) : esc_html_e( '没有找到相关文章，试试上方匹配到的用户吧。', 'fanimeta' ); ?></p>
						<?php endif; ?>
					</div>

				</div>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
