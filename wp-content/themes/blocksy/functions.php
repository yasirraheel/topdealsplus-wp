<?php
/**
 * Blocksy functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Blocksy
 */

if (version_compare(PHP_VERSION, '5.7.0', '<')) {
	require get_template_directory() . '/inc/php-fallback.php';
	return;
}

require get_template_directory() . '/inc/init.php';

/**
 * Keep the footer credit focused on the site brand instead of the theme author.
 */
add_filter('blocksy:footer:copyright:value', function () {
	return 'Copyright &copy; {current_year} {site_title}. All rights reserved.';
});

/**
 * Blocksy can omit style.css from the front-end bundle, so keep the brand
 * replacement available inline as well.
 */
add_action('wp_head', function () {
	if (is_admin()) {
		return;
	}

	echo '<style id="topdealsplus-brand-overrides">
		header .site-branding .site-logo-container img,
		header .site-branding .site-logo-container svg {
			display: none !important;
		}
		header .site-branding .site-logo-container {
			display: inline-flex !important;
			align-items: center;
			font-size: 32px !important;
			font-weight: 800 !important;
			line-height: 1 !important;
			letter-spacing: -0.04em;
			text-decoration: none;
		}
		header .site-branding .site-logo-container::before {
			content: "🛒";
			display: inline-block;
			margin-right: 10px;
			font-size: 25px;
			line-height: 1;
		}
		header .site-branding .site-logo-container::after {
			content: "Top Deals Plus";
		}
		@media (max-width: 768px) {
			header .site-branding .site-logo-container {
				font-size: 25px !important;
			}
			header .site-branding .site-logo-container::before {
				margin-right: 7px;
				font-size: 20px;
			}
		}
	</style>';
});

/**
 * Show the latest posts directly below the front-page hero.
 */
function topdealsplus_front_page_posts() {
	if (! is_front_page()) {
		return;
	}

	$posts_query = new WP_Query([
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 6,
		'ignore_sticky_posts' => true,
	]);

	if (! $posts_query->have_posts()) {
		return;
	}
	?>
	<section class="topdealsplus-home-posts" aria-labelledby="topdealsplus-home-posts-title">
		<div class="ct-container">
			<div class="topdealsplus-home-posts-heading">
				<p class="topdealsplus-eyebrow"><?php echo esc_html__('From the blog', 'blocksy'); ?></p>
				<h2 id="topdealsplus-home-posts-title"><?php echo esc_html__('Latest deals and insights', 'blocksy'); ?></h2>
			</div>

			<div class="topdealsplus-home-posts-grid">
				<?php while ($posts_query->have_posts()) : $posts_query->the_post(); ?>
					<article <?php post_class('topdealsplus-home-post-card'); ?>>
						<a class="topdealsplus-home-post-image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('Read: %s', 'blocksy'), get_the_title())); ?>">
							<?php if (has_post_thumbnail()) : ?>
								<?php the_post_thumbnail('medium_large'); ?>
							<?php else : ?>
								<span class="topdealsplus-home-post-image-placeholder" aria-hidden="true"></span>
							<?php endif; ?>
						</a>
						<div class="topdealsplus-home-post-content">
							<p class="topdealsplus-home-post-meta"><?php echo esc_html(get_the_date()); ?></p>
							<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 22)); ?></p>
							<a class="topdealsplus-home-post-link" href="<?php the_permalink(); ?>"><?php echo esc_html__('Read more', 'blocksy'); ?> <span aria-hidden="true">→</span></a>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
		</div>
	</section>
	<?php

	wp_reset_postdata();
}
add_action('blocksy:single:container:top', 'topdealsplus_front_page_posts');
