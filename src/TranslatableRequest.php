<?php
namespace Minhbang\Locale;

use Minhbang\Kit\Extensions\Request;
use LocaleManager;

/**
 * Class TranslatableRequest
 * Khi sử dụng TranslatableRequest, rules của các Translatable Attribute phải bỏ required
 *
 * @package Minhbang\Locale
 */
abstract class TranslatableRequest extends Request
{
    /**
     * Danh sách tên các attributes đa ngôn ngữ: ['attr1', 'attr2',...]
     *
     * @var array
     */
    protected $translatable = [];

    /**
     * Bổ sung các translation attributes, $attribute[$locale.$attribute] = ...
     *
     * @return array
     */
    public function attributes()
    {
        return $this->translatableAttributes(parent::attributes());
    }

    /**
     * Hàm này được gọi trước khi Validate,
     * Nên tận dụng để thêm các translatable attribute rules
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        $this->translatableBuildRules();

        return parent::getValidatorInstance();
    }

    /**
     * @param array $items
     *
     * @return array
     */
    protected function translatableAttributes($items)
    {
        if ($this->translatable && $items) {
            foreach ($this->translatable as $attribute) {
                if (isset($items[$attribute])) {
                    foreach (LocaleManager::all(true) as $locale) {
                        $items["{$locale}.$attribute"] = $items[$attribute];
                    }
                    unset($items[$attribute]);
                }
            }
        }

        return $items;
    }

    /**
     * Tạo các rules cho translatable attributes
     * Chỉ giữ lại rule 'required' cho locale fallback (nếu có)
     */
    protected function translatableBuildRules()
    {
        if ($this->translatable && $this->rules) {
            $fallback = LocaleManager::getFallback();
            foreach ($this->translatable as $attribute) {
                if (isset($this->rules[$attribute])) {
                    // Xóa rule 'required'
                    $rule = str_replace('required', '', $this->rules[$attribute], $count);
                    if ($count) {
                        $rule = trim(str_replace('||', '|', $rule), '|');
                    }
                    // Tạo translatable attribute rules, vd: vi.name, en.name,...
                    foreach (LocaleManager::all(true) as $locale) {
                        $this->rules["{$locale}.$attribute"] = $locale == $fallback ? $this->rules[$attribute] : $rule;
                    }

                    unset($this->rules[$attribute]);
                }
            }
        }
    }

    /**
     * @param string $attribute
     * @param string $rule
     */
    protected function translatableAddRule($attribute, $rule)
    {
        if (in_array($attribute, $this->translatable)) {
            foreach (LocaleManager::all(true) as $locale) {
                if (isset($this->rules["{$locale}.{$attribute}"])) {
                    $this->rules["{$locale}.{$attribute}"] .= "|$rule";
                } else {
                    $this->rules["{$locale}.{$attribute}"] = $rule;
                }
            }
        }
    }
}