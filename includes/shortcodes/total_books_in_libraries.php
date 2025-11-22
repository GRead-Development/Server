<?php

// A shortcode to display a count of how many books have been added to users' libraries, collectively.

function hs_shortcode_total_books_in_libraries($atts)
{
	$atts = shortcode_atts(array(
		'format' => 'number', // May be better off as text, but IDK
	), $atts);

	$count = hs_get_total
