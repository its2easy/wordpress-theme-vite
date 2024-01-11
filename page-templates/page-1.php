<?php
/**
 * Template Name: Page 1
 */
get_header();
while (have_posts()) :
	the_post();
	?>
<section>
	<h1>Page-1 template</h1>
</section>
<?php
endwhile;
get_footer();
