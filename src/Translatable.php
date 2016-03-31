<?php
namespace Minhbang\Locale;

use LocaleManager;
use Illuminate\Database\Eloquent\Builder;
//use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\MassAssignmentException;

/**
 * Class Translatable
 *
 * @package Minhbang\Locale
 *
 * @property array $translatable
 * @property-read string $table
 * @property-read bool $exists
 * @property-read \Illuminate\Database\Eloquent\Collection $translations
 * @method static bool getDirty()
 * @mixin \Minhbang\Kit\Extensions\Model
 */
trait Translatable
{
    public static $translation_table;
    public static $translation_model;

    public static function bootTranslatable()
    {
        static::$translation_model = static::class . 'Translation';
        static::$translation_table = (new static::$translation_model())->getTable();

    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany(static::$translation_model);
    }

    /**
     * Key có thể: 'attribute', 'attribute:locale', 'attribute:locale|no_fallback'
     *
     * @param string $key
     * @param string $locale
     * @param string $default
     */
    protected function parserTranslationKey(&$key, &$locale, &$default = null)
    {
        if (str_contains($key, ':')) {
            list($key, $locale) = explode(':', $key, 2);
            if (str_contains($locale, '|')) {
                list($locale, $default) = explode('|', $locale, 2);
            } else {
                $default = null;
            }
        } else {
            $locale = LocaleManager::getLocale();
            $default = null;
        }
    }
    //------------------------------------------------------------------------------------------------------------------
    /**
     * Alias for getTranslation()
     *
     * @param string|null $locale
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function translate($locale = null)
    {
        return $this->getTranslation($locale);
    }

    /**
     * Alias for getTranslationOrNew()
     *
     * @param string $locale
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function translateOrNew($locale)
    {
        return $this->getTranslationOrNew($locale);
    }

    /**
     * @param string|null $locale
     * @param bool $fallback
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getTranslation($locale = null, $fallback = true)
    {
        $translation = $this->translations->where('locale', LocaleManager::getLocale($locale))->first();
        if ($fallback && is_null($translation)) {
            $translation = $this->translations->where('locale', LocaleManager::getFallback())->first();
        }

        return $translation;
    }

    /**
     * @param string|null $locale
     *
     * @return bool
     */
    public function hasTranslation($locale = null)
    {
        return $this->translations->contains('locale', LocaleManager::getLocale($locale));
    }

    /**
     * Get model attribute (nếu không phải translatable attr thì get bình thường)
     * - $model->attr lấy translation của locale hiện tại (app.locale)
     * - $model->{attr:locale} lấy translation của locale CÓ fallback
     * - $model->{attr:locale|default} lấy translation của locale KHÔNG fallback, không có sẽ trả về 'default'
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        $this->parserTranslationKey($key, $locale, $default);
        if ($this->isTranslationAttribute($key)) {
            if ($translation = $this->getTranslation($locale, $default === null)) {
                return $translation->$key;
            } else {
                return $default;
            }
        }

        return parent::getAttribute($key);
    }


    //TODO: ImageableModel sử dụng 2 hàm getAttributeRaw và getOriginal => Kiểm tra làm việc OK
    /**
     * Lấy giá trị Raw của attribute,
     *
     * @param $key
     *
     * @return mixed
     */
    public function getAttributeRaw($key)
    {
        // Vì Translation Model không có bất kỳ Accessors hoặc Mutators
        return $this->getAttribute($key);
    }

    /**
     * Get the model's original attribute values.
     *
     * @param  string|null $key
     * @param  mixed $default
     *
     * @return array
     */
    public function getOriginal($key = null, $default = null)
    {
        $this->parserTranslationKey($key, $locale, $default);
        if ($this->isTranslationAttribute($key)) {
            if ($translation = $this->getTranslation($locale, $default === null)) {
                return $translation->getOriginal($key, $default);
            } else {
                return $default;
            }
        }

        return parent::getOriginal($key, $default);
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return static
     */
    public function setAttribute($key, $value)
    {
        $this->parserTranslationKey($key, $locale);
        if ($this->isTranslationAttribute($key)) {
            $this->getTranslationOrNew($locale)->$key = $value;
        } else {
            return parent::setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * @param array $options
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            if (count($this->getDirty()) > 0) {
                // If $this->exists and dirty, parent::save() has to return true. If not,
                // an error has occurred. Therefore we shouldn't save the translations.
                if (parent::save($options)) {
                    return $this->saveTranslations();
                }

                return false;
            } else {
                // If $this->exists and not dirty, parent::save() skips saving and returns
                // false. So we have to save the translations
                if ($saved = $this->saveTranslations()) {
                    $this->fireModelEvent('saved', false);
                    $this->fireModelEvent('updated', false);
                }

                return $saved;
            }
        } elseif (parent::save($options)) {
            // We save the translations only if the instance is saved in the database.
            return $this->saveTranslations();
        }

        return false;
    }

    /**
     * @param string $locale
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getTranslationOrNew($locale)
    {
        if (($translation = $this->getTranslation($locale, false)) === null) {
            $translation = $this->getNewTranslation($locale);
        }

        return $translation;
    }

    /**
     * Tham số $attributes:
     * Đối với translation attributes, ví dụ: 'name', 'slug'
     * [
     *     'vi' => ['Ten', 'Ten SEO'],
     *     'en' => ['Name', 'Slug'],
     * ]
     * Các attribute khác bình thường
     *
     * @param array $attributes
     *
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($attributes as $key => $values) {
            // Nếu $key là một locale
            if (LocaleManager::has($key)) {
                foreach ($values as $attribute => $value) {
                    if ($this->isFillable($attribute)) {
                        $this->getTranslationOrNew($key)->$attribute = $value;
                    } elseif ($totallyGuarded) {
                        throw new MassAssignmentException($key);
                    }
                }
                unset($attributes[$key]);
            }
        }

        return parent::fill($attributes);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isTranslationAttribute($key)
    {
        return in_array($key, $this->translatable);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getTranslationAttributeColumn($key)
    {
        return ($this->isTranslationAttribute($key) ? static::$translation_table : $this->table) . '.' . $key;
    }

    /**
     * @return bool
     */
    protected function saveTranslations()
    {
        $saved = true;
        foreach ($this->translations as $translation) {
            if ($saved && $this->isTranslationDirty($translation)) {
                $translation->setAttribute($this->getForeignKey(), $this->getKey());
                $saved = $translation->save();
            }
        }

        return $saved;
    }

    /**
     * @param static $translation
     *
     * @return bool
     */
    protected function isTranslationDirty($translation)
    {
        $dirtyAttributes = $translation->getDirty();
        unset($dirtyAttributes['locale']);

        return count($dirtyAttributes) > 0;
    }

    /**
     * @param string $locale
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getNewTranslation($locale)
    {
        $translation = new static::$translation_model();
        $translation->setAttribute('locale', $locale);
        $this->translations->add($translation);

        return $translation;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return ($this->isTranslationAttribute($key) || parent::__isset($key));
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $attributes = parent::toArray();
        if ($translations = $this->getTranslation()) {
            $hiddenAttributes = $this->getHidden();
            foreach ($this->translatable as $field) {
                if (!in_array($field, $hiddenAttributes)) {
                    $attributes[$field] = $translations->$field;
                }
            }
        }

        return $attributes;
    }

    /**
     * This scope filters results by checking the translation fields.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param string $value
     * @param string $locale
     * @param string $operator ('=' | 'LIKE')
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function scopeWhereTranslation($query, $key, $value, $locale = null, $operator = '=')
    {
        return $query->whereHas('translations', function ($query) use ($key, $value, $locale, $operator) {
            $query->where(static::$translation_table . '.' . $key, $operator, $value);
            if ($locale) {
                $query->where(static::$translation_table . '.' . 'locale', $operator, $locale);
            }
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $locale
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function scopeTranslatedIn(Builder $query, $locale = null)
    {
        $locale = LocaleManager::getLocale($locale);

        return $query->whereHas('translations', function (Builder $q) use ($locale) {
            $q->where('locale', '=', $locale);
        });
    }

    /**
     * Adds scope to get a list of translated attributes, using the current locale.
     *
     * Example usage: Country::listsTranslations('name')->get()->toArray()
     * Will return an array with items:
     *  [
     *      'id' => '1',                // The id of country
     *      'name' => 'Griechenland'    // The translated name
     *  ]
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field translation field
     */
    /*public function scopeListsTranslations(Builder $query, $field)
    {
        $id_key = $this->table . '.' . $this->getKeyName();
        $foreign_key = static::$translation_table . '.' . $this->getForeignKey();
        $locale_key = static::$translation_table . '.locale';

        $query
            ->select($id_key, static::$translation_table . '.' . $field)
            ->leftJoin(static::$translation_table, $foreign_key, '=', $id_key)
            ->where($locale_key, LocaleManager::getLocale())
            ->orWhere(function (Builder $q) use ($foreign_key, $locale_key) {
                $q->where($locale_key, LocaleManager::getFallback())
                    ->whereNotIn($foreign_key, function (QueryBuilder $q) use ($foreign_key, $locale_key) {
                        $q->select($foreign_key)
                            ->from(static::$translation_table)
                            ->where($locale_key, LocaleManager::getLocale());
                    });
            });
    }*/

    /**
     * This scope eager loads the translations for the default and the fallback locale only.
     * We can use this as a shortcut to improve performance in our application.
     *
     * @param Builder $query
     */
    /*public function scopeWithTranslation(Builder $query)
    {
        $query->with(['translations' => function ($query) {
            $query->where(static::$translation_table . '.' . 'locale', LocaleManager::getLocale())
                ->orWhere(static::$translation_table . '.' . 'locale', LocaleManager::getFallback());
        }]);
    }*/
}
