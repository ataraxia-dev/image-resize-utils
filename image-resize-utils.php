<?php

/*
 * Plugin Name: Image Utils
 * Description: Image crop & resize helper functions for theme development use.
 * Version: 0.0.1
 * Author: Ataraxia Development
 */

/* image helper functions begin here */
function imgutils_resize_image( $id, $w, $h ) {
    $old_path = is_numeric( $id ) ? get_attached_file( $id ) : $id;
    $suffix = '-r-' . ( $w ? $w : '_' ) . 'x' . ( $h ? $h : '_' );
    $path = preg_replace( '/(.*)(\.[^.]+)$/', '$1' . $suffix . '$2', $old_path );
    /* don't resize if we can't make a new path for some reason!
     * possibly a missing file extension? */
    if( $path !== $old_path) {
        if( ! file_exists( $path ) ) {
            $crop = $w !== null && $h !== null;
            $target_w = $w;
            $target_h = $h;
            $image = wp_get_image_editor( $old_path );
            if(!($image instanceof WP_Error)) {
                list( $curr_w, $curr_h ) = $image->get_size();
                /* if the image is too small, scale/crop to the largest
                 * available size with the same proportions (unfortunately 
                 * this "lies" about the image size in the url... to fix
                 * later?) */
                $size = $image->get_size();
                $curr_h = $size['height'];
                $curr_w = $size['width'];
                if( is_int( $target_w ) && $target_w > $curr_w ) {
                    if( is_int( $target_h ) ) { 
                        $ratio = $curr_w / $target_w;
                        $target_h *= $ratio;
                    }
                    $target_w = $curr_w;
                }
                if( is_int( $target_h ) && $target_h > $curr_h ) {
                    if( is_int( $target_w ) ) { 
                        $ratio = $curr_h / $target_h;
                        $target_w *= $ratio;
                    }
                    $target_h = $curr_h;
                }
                $image->resize( $target_w, $target_h, $crop );
                $image->save( $path );
            }
            else {}
        }
    }
    $size = wp_getimagesize( $path );
    return [
        'w' => $size[0],
        'h' => $size[1],
        'url' => str_replace( ABSPATH, get_site_url() . "/", $path ),
    ];
}

function imgutils_image_base( $id, $args ) {
    $alt = null;
    if( is_numeric( $id ) ) {
        $alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
    }
    $args = wp_parse_args( $args, [
        'w' => null,
        'h' => null,
        'class' => '',
        'alt'   => $alt,
    ] );
    $resized = imgutils_resize_image( $id, $args['w'], $args['h'] );
    if( $args['w'] ) {
        $args['w'] *= 2;
    }
    if( $args['h'] ) {
        $args['h'] *= 2;
    }
    $resized_2x = imgutils_resize_image( $id, $args['w'], $args['h'] );
    return [
        'w' => $resized['w'],
        'h' => $resized['h'],
        'alt' => $args['alt'],
        'class'  => $args['class'],
        'src'    => $resized['url'],
        'srcset' => [
            '1x' => $resized['url'],
            '2x' => $resized_2x['url'],
        ]
    ];
}

function imgutils_image( $id, $args ) {
    $base = imgutils_image_base( $id, $args );
    return sprintf(
       "<img src='%s' width='%s' height='%s' alt='%s' class='%s' srcset='%s' />",
       $base['src'],
       $base['w'],
       $base['h'],
       $base['alt'],
       $base['class'],
       sprintf( "%s 1x, %s 2x", $base['srcset']['1x'], $base['srcset']['2x'] ),
   );
}

function imgutils_background_image( $id, $sel, $args ) {
    $base = imgutils_image_base( $id, $args );
    echo $sel . " { background-image: url(" . $base["srcset"]["1x"] . "); }\n";
    echo "@media(min-resolution: 192dpi) {\n";
    echo "\t" . $sel . " { background-image: url(" . $base["srcset"]["2x"] . "); }\n";
    echo "}\n";
}
