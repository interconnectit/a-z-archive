# A-Z Archive

## Features

* Post type archive that lists posts in alphabetical order.
* Filter posts by letter or symbol.

## Requirements

* PHP >= 8.1
* WordPress >= 5.8

## Usage

Set the `alpha_sort` support for your post type.
```php
register_post_type( 'post_type_name', [
    ...,
    'supports' => [ 'title', 'editor', 'thumbnail', 'alpha_sort' ],
    ...
] );
```
Or
```php
add_post_type_support( 'post_type_name', 'alpha_sort' );
```

To show the filters on the post type archive page use the following code in your theme template file.
```php
ICIT\StandFirst\PostSort\AtoZ::post_type_alpha_filters( 'post_type_name', [ 'title'=> 'Filters', 'all_title' => 'All Posts' ] );
```
