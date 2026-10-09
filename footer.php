<?php
/**
 * 页面底部
 *
 * @package Fanimeta
 */
?>

	<div class="back-to-top" aria-label="<?php esc_attr_e( '回到顶部', 'fanimeta' ); ?>" title="<?php esc_attr_e( '回到顶部', 'fanimeta' ); ?>">
		<i class="fa fa-arrow-up" aria-hidden="true"></i>
	</div>

	<footer class="site-footer" itemscope itemtype="https://schema.org/WPFooter">
		<div class="footer-inner">
			<?php
			$fanimeta_copyright = get_theme_mod( 'fanimeta_copyright', '&copy; {year} <a href="{site_url}">{site_name}</a> · 版权所有' );
			if ( $fanimeta_copyright ) :
				?>
				<div class="copyright">
					<?php echo wp_kses_post( fanimeta_footer_tokens( $fanimeta_copyright ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
				<div class="footer-widgets">
					<?php dynamic_sidebar( 'footer-1' ); ?>
				</div>
			<?php endif; ?>

			<?php
			$fanimeta_footer_text = get_theme_mod( 'fanimeta_footer_text' );
			if ( $fanimeta_footer_text ) :
				?>
				<div class="footer-custom">
					<?php echo wp_kses_post( wpautop( $fanimeta_footer_text ) ); ?>
				</div>
			<?php endif; ?>

			<?php
			$fanimeta_powered_by = get_theme_mod( 'fanimeta_powered_by', '由 <a href="https://wordpress.org/" target="_blank" rel="noopener">WordPress</a> &amp; <a href="{site_url}">Fanimeta</a> 强力驱动' );
			if ( $fanimeta_powered_by ) :
				?>
				<div class="powered-by">
					<?php echo wp_kses_post( fanimeta_footer_tokens( $fanimeta_powered_by ) ); ?>
				</div>
			<?php endif; ?>

			<?php
			$fanimeta_icp             = get_theme_mod( 'fanimeta_icp' );
			$fanimeta_public_security = get_theme_mod( 'fanimeta_public_security' );
			if ( $fanimeta_icp || $fanimeta_public_security ) :
				?>
				<div class="beian">
					<?php if ( $fanimeta_icp ) : ?>
						<a class="beian-item" href="https://beian.miit.gov.cn/" target="_blank" rel="noopener nofollow"><?php echo esc_html( $fanimeta_icp ); ?></a>
					<?php endif; ?>

					<?php if ( $fanimeta_public_security ) : ?>
						<a class="beian-item beian-police" href="https://beian.mps.gov.cn/" target="_blank" rel="noopener nofollow">
							<img class="beian-police-icon" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/police-badge.png' ); ?>" alt="<?php esc_attr_e( '公安备案图标', 'fanimeta' ); ?>">
							<?php echo esc_html( $fanimeta_public_security ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</footer>

</div>

<?php wp_footer(); ?>
</body>
</html>
