<?php
  //カスタム投稿のカテゴリー：カテゴリー選択ボックスで一つだけしか選択できないようにする。
  add_action( 'admin_print_footer_scripts', 'select_to_radio_tags' );
  function select_to_radio_tags() {
      ?>
      <script type="text/javascript">
      jQuery( function( $ ) {
          // 投稿画面
          $( '#taxonomy-tags input[type=checkbox]' ).each( function() {
              $( this ).replaceWith( $( this ).clone().attr( 'type', 'radio' ) );
          } );

          // 一覧画面
          let event_cat_checklist = $( '.tagschecklist input[type=checkbox]' );
          event_cat_checklist.click( function() {
            $( this ).closest( '.tagschecklist' ).find( ' input[type=checkbox]' ).not(this).prop( 'checked', false );
          } );
      } );
      </script>
      <?php
  }

  function my_customize_rest_cors() {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
    add_filter( 'rest_pre_serve_request', function( $value ) {
      header( 'Access-Control-Allow-Origin: *' );
      header( 'Access-Control-Allow-Methods: GET' );
      header( 'Access-Control-Allow-Credentials: true' );
      header( 'Access-Control-Expose-Headers: Link', false );
      header( 'Access-Control-Allow-Headers: X-Requested-With' );

      return $value;
    });
  }
  add_action( 'rest_api_init', 'my_customize_rest_cors', 15 );

  add_theme_support( 'post-thumbnails' );
  // 画像追加（これはもういらないかも）
  // add_action('rest_api_init', 'customize_api_response');
  // function customize_api_response() {
  //   register_rest_field(
  //     'post',
  //     'thumbnail',
  //     array(
  //       'get_callback'  => function ($post) {
  //         $thumbnail_id = get_post_thumbnail_id($post['id']);

  //         if ($thumbnail_id) {
  //           // アイキャッチが設定されていたらurl・width・heightを配列で返す
  //           $img = wp_get_attachment_image_src($thumbnail_id, 'large');

  //           return [
  //             'url' => $img[0],
  //             'width' => $img[1],
  //             'height' => $img[2]
  //           ];
  //         } else {
  //           // アイキャッチが設定されていなかったら空の配列を返す
  //           return [];
  //         }
  //       },
  //       'update_callback' => null,
  //       'schema'          => null,
  //     )
  //   );
  // }

  // 投稿タイプ：postを全件取得＋レスポンスを加工
  function add_rest_endpoint_all_posts_from_blog() {
    register_rest_route(
      'wp/api',
      '/post',
      array(
        'methods' => 'GET',
        'callback' => 'get_all_posts_from_blog',
        'permission_callback' => function() { return true; }
      )
    );
  }
  function get_all_posts_from_blog() {
    $args = array(
      'posts_per_page' => $_GET['perpage'],
      'post_type' => 'post',
      'post_status' => 'publish',
      'paged' => $_GET['pagenation']
    );
    $all_posts = get_posts($args);
    $result = array();
    foreach($all_posts as $post) {
      $data = array(
        'id' => $post->ID,
        'thumbnail' => get_the_post_thumbnail_url($post->ID, 'full'),
        'slug' => $post->post_name,
        'date' => $post->post_date,
        'modified' => $post->post_modified,
        'title' => $post->post_title,
        'excerpt' => $post->post_excerpt,
        'content' => $post->post_content,
        'category' => get_the_category($post->ID),
        'tag' => get_the_tags($post->ID)
      );
      array_push($result, $data);
    };
    return $result;
  }
  add_action('rest_api_init', 'add_rest_endpoint_all_posts_from_blog');

  // キーワード検索
  function add_rest_endpoint_all_posts_search() {
    register_rest_route(
      'wp/api',
      '/search/(?P<keywords>.*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+)',
      array(
        'methods' => 'GET',
        'callback' => 'get_all_posts_search',
        'permission_callback' => function() { return true; }
      )
    );
  }
  function get_all_posts_search($parameter) {
    $args = array(
      'posts_per_page' => -1,
      'post_type' => array( 'post', 'page', 'blog', 'news' ),
      's' => urldecode($parameter['keywords']),
      'post_status' => 'publish'
    );
    $query = new WP_Query($args);
    $all_posts = $query->posts;
    $result = array();
    foreach($all_posts as $post) {
      $category = '';
      if($post->post_type === 'post') {
        $category = get_the_terms($post->ID, 'category')[0]->name;
      } else if($post->post_type === 'blog') {
        $category = get_the_terms($post->ID, 'blog_category')[0]->name;
      } else if($post->post_type === 'news') {
        $category = get_the_terms($post->ID, 'news_category')[0]->name;
      }
      $data = array(
        'ID' => $post->ID,
        'thumbnail' => get_the_post_thumbnail_url($post->ID, 'full'),
        'slug' => $post->post_name,
        'date' => $post->post_date,
        'modified' => $post->post_modified,
        'title' => $post->post_title,
        'excerpt' => $post->post_excerpt,
        'content' => $post->post_content,
        'category' => $category,
        'post_type' => $post_type
      );
      array_push($result, $data);
    };
    return $result;
  }
  add_action('rest_api_init', 'add_rest_endpoint_all_posts_search');

  // ID指定で個別の記事を取得
  function add_rest_endpoint_single_posts() {
    register_rest_route(
      'wp/api',
      '/blog/(?P<id>[\d]+)',
      array(
        'methods' => 'GET',
        'callback' => 'get_single_posts',
        'permission_callback' => function() { return true; }
      )
    );
  }
  function get_single_posts($parameter) {
    $args_all = array(
      'posts_per_page' => -1,
      'post_type' => 'post',
      'post_status' => 'publish',
      'orderby' => 'date',
      'order' => 'DESC',
    );
    $all_posts = get_posts($args_all);
    $all_posts_ids = array();
    foreach($all_posts as $post) {
      array_push($all_posts_ids, $post->ID);
    }

    $args_single = array(
      'posts_per_page' => 1,
      'post_type' => 'post',
      'post_status' => 'publish',
      'include' => $parameter['id']
    );
    $single_post = get_posts($args_single);
    $single_post_index = !empty($single_post) ? array_search((int) $parameter['id'], $all_posts_ids, true) : -2;
    $prev_post_id = $single_post_index < count($all_posts_ids) - 1 ? $single_post_index + 1 : null;
    $next_post_id = !is_null($single_post_index) && ($single_post_index > 0) ? $single_post_index - 1 : null;
    $targets = array($all_posts[$next_post_id], $single_post[0], $all_posts[$prev_post_id]);
    $result = array();
    foreach($targets as $post) {
      $data = array(
        'ID' => $post->ID,
        'thumbnail' => get_the_post_thumbnail_url($post->ID, 'full'),
        'slug' => $post->post_name,
        'date' => $post->post_date,
        'modified' => $post->post_modified,
        'title' => $post->post_title,
        'excerpt' => $post->post_excerpt,
        'content' => $post->post_content,
        'category' => get_the_category($post->ID)
      );
      array_push($result, $data);
    };
    return $result;
  }
  add_action('rest_api_init', 'add_rest_endpoint_single_posts');

  // カテゴリー追加
  // function api_add_fields() {
  //   register_rest_field( 'post',
  //   'cat_info',
  //   array(
  //     'get_callback'    => 'register_fields',
  //     'update_callback' => null,
  //     'schema'          => null,
  //     )
  //   );
  // }
  // function register_fields( $post, $name ) {
  //   return get_the_category($post['id']);
  // }
  // var_dump(api_add_fields());
  // add_action( 'rest_api_init', 'api_add_fields' );

  function add_rest_endpoint_all_news() {
    register_rest_route(
      'wp/api',
      '/news',
      array(
        'methods' => 'GET',
        'callback' => 'get_all_news',
        'permission_callback' => function() { return true; }
      )
    );
  }
  function get_all_news() {
    $my_posts = [];
    $i = 0;
    $tags = get_terms('tags');
    foreach($tags as $tag) {
      $args = array(
        'post_type' => 'news',
        // 'paged' => get_query_var('page'),
        // 'order' => 'ASC',
        'tax_query' => array(
          array(
            'taxonomy' => 'tags',
            'field'    => 'slug',
            'terms'    => $tag -> slug,
          ),
        ),
        'posts_per_page' => -1,
      );
      $WP_post = new WP_Query($args);
      // $my_posts['total'] = $WP_post -> found_posts;
      // var_dump($WP_post -> post_count);
      if ($WP_post -> have_posts()) {
        while ($WP_post -> have_posts()) {
          $WP_post->the_post();
          ++$i;
          $post_data = [];
          $post_data['id'] = $i;
          $post_data['category'] = $tag -> name;
          $post_data['title'] = get_field('title') ? get_field('title') : '';
          $post_data['body'] = get_field('body') ? get_field('body') : '';
          $post_data['thumbnails'] = get_field('thumbnails');
          $post_data['updatedAt'] = get_the_modified_date('c');
          $my_posts['data'][] = $post_data;
        }
      }
    }
    return $my_posts;
  }
  add_action('rest_api_init', 'add_rest_endpoint_all_news');