<?php
namespace Minhbang\Locale;

use Closure;
use LocaleManager;

/**
 * Class LocaleMiddleware
 * Xử lý thiết lập app locale
 *
 * @package Minhbang\Locale
 */
class LocaleMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $segments = $request->segments();
        if (LocaleManager::ignored($segments)) {
            return $next($request);
        }

        // thiết lập locale bằng link set locale: /locale/{locale}
        if ($locale = LocaleManager::parserLocale($segments)) {
            if (LocaleManager::has($locale)) {
                session(['locale' => $locale]);

                return back()->withCookie(cookie()->forever('locale', $locale));
            } else {
                abort(404, trans('errors.invalid_request'));
            }
        } else {
            if ($locale = LocaleManager::newLocale($request)) {
                app()->setLocale($locale);
                session(['locale' => $locale]);

                return $next($request)->withCookie(cookie()->forever('locale', $locale));
            }
        }

        return $next($request);
    }
}
