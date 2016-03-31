<?php
namespace Minhbang\Locale;

/**
 * Class Manager
 *
 * @package Minhbang\Locale
 */
class Manager
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * Locale constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->config = array_merge(config('locale'), $config);
    }

    /**
     * @param bool $name_only
     *
     * @return array
     */
    public function all($name_only = false)
    {
        return $name_only ? array_keys($this->config['all']) : $this->config['all'];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return isset($this->config['all'][$name]);
    }

    /**
     * @param array $segments
     *
     * @return bool
     */
    public function ignored($segments)
    {
        return $segments && in_array($segments[0], (array)$this->config['ignored']);
    }

    /**
     * @return string
     */
    public function getFallback()
    {
        return $this->config['fallback'];
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function css($name)
    {
        return isset($this->config['css'][$name]) ? $this->config['css'][$name] : null;
    }

    /**
     * @param string $locale
     *
     * @return string
     */
    public function getLocale($locale = null)
    {
        return $locale ?: config('app.locale');
    }

    /**
     * @param string $name
     *
     * @return string|null
     */
    public function getTitle($name)
    {
        return $this->has($name) ? $this->config['all'][$name] : null;
    }

    /**
     * Lấy $locale khi user request '/locale/{locale}'
     *
     * @param array $segments
     * @param mixed $default
     *
     * @return null|string
     */
    public function parserLocale($segments, $default = null)
    {
        return $segments && count($segments === 2) && $segments[0] === 'locale' ? $segments[1] : $default;
    }

    /**
     * Thứ tự ưu tiên set locale: input > session > cookie > config('app.locale')
     *
     * @param  \Illuminate\Http\Request $request
     * @param mixed $default
     *
     * @return string
     */
    public function newLocale($request, $default = null)
    {
        $locale = $request->input('locale', session('locale', $request->cookie('locale', config('app.locale'))));

        return $locale && $this->has($locale) ? $locale : $default;
    }

    /**
     * @return array
     */
    public function compact()
    {
        return ['locales' => $this->all(), 'active_locale' => $this->getLocale()];
    }
}