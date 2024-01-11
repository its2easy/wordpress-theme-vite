<?php
/**
 * Template Name: Page 2
 */
get_header();
while (have_posts()) :
	the_post();
	?>
	<section>
		<h1>Page-2 template</h1>
	</section>
<?php
endwhile;
get_footer();
