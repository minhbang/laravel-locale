# Laravel Locale

Tính năng đa ngôn ngữ cho Eloquent

## Install

* **Thêm vào file composer.json của app**
```json
	"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/minhbang/laravel-locale"
        }
    ],
    "require": {
        "minhbang/laravel-locale": "dev-master"
    }
```
``` bash
$ composer update
```

* **Thêm vào file config/app.php => 'providers'**
```php
	Minhbang\Locale\ServiceProvider::class,
```

* **Publish config và database migrations**
```bash
$ php artisan vendor:publish
```

* **Thêm vào file app/Http/Kernel.php => $routeMiddleware** (đứng đầu)
Middleware xử lý user thay đổi App Locale

```php
protected $routeMiddleware = [
	'role' => \Minhbang\Locale\LocaleMiddleware::class,
	//...
];
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
