<?php
/**
 * [RevisionControl]
 *
 */
return [
	'RevisionControl' => [
		'limit' 	=> 50,	// 世代制限 0:無し | 数値
		'displayLimit' => 20,	// 表示件数
		'models'	=> [
			'BcBlog.BlogPosts' => ['BlogPosts', 'BlogTag'],
			'BaserCore.Pages' => ['Pages'],
		],
		'actsAs'	=> [
			'BcUpload' => [
				'BlogPosts' => [
					'eye_catch'
				]
			]
		],
		'views' => [
			'BcBlog.BlogPosts' => ['controller'=> 'BlogPosts', 'action' => 'edit', 'data' => 'post'],
			'BaserCore.Pages' => ['controller'=> 'Pages', 'action' => 'edit', 'data' => 'page']
		],
		'filesDir' => '_rvc',

		// 除外フォーム
		'excludeFormId' => [
			'FavoriteAjaxForm',
			'PermissionAjaxAddForm',
		]
	],
];
