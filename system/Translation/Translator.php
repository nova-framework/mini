<?php

namespace Mini\Translation;

use Mini\Filesystem\Filesystem;

use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\TranslatorInterface;


class Translator implements TranslatorInterface
{
    /**
     * The filesystem instance.
     *
     * @var \Mini\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The default path for the loader.
     *
     * @var string
     */
    protected $path;

    /**
     * The default locale being used by the translator.
     *
     * @var string
     */
    protected $locale;

    /**
     * The fallback locale used by the translator.
     *
     * @var string
     */
    protected $fallback;

    /**
     * The array of loaded translation groups.
     *
     * @var array
     */
    protected $loaded = array();


    /**
     * Create a new translator instance.
     *
     * @param  \Mini\Filesystem\Filesystem  $files
     * @param  string  $path
     * @param  string  $locale
     * @return void
     */
    public function __construct(Filesystem $files, $path, $locale)
    {
        $this->files  = $files;
        $this->path   = $path;
        $this->locale = $locale;
    }

    /**
     * Determine if a translation exists.
     *
     * @param  string  $key
     * @param  string  $locale
     * @return bool
     */
    public function has($key, $locale = null)
    {
        return $this->get($key, array(), $locale) !== $key;
    }

    /**
     * Get the translation for the given key.
     *
     * @param  string  $key
     * @param  array   $replace
     * @param  string  $locale
     * @return string
     */
    public function get($key, array $replace = array(), $locale = null)
    {
        list ($group, $item) = array_pad(explode('.', $key, 2), 2, null);

        foreach ($this->parseLocale($locale) as $locale) {
            $this->load($group, $locale);

            if (! is_null($line = $this->getLine($group, $locale, $item, $replace))) {
                break;
            }
        }

        if (! isset($line)) {
            return $key;
        }

        return $line;
    }

    /**
     * Retrieve a language line out the loaded array.
     *
     * @param  string  $namespace
     * @param  string  $group
     * @param  string  $locale
     * @param  string  $item
     * @param  array   $replace
     * @return string|null
     */
    protected function getLine($group, $locale, $item, array $replace)
    {
        $line = array_get($this->loaded[$group][$locale], $item);

        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        }

        // The line is not a string.
        else if (is_array($line) && (count($line) > 0)) {
            return $line;
        }
    }

    /**
     * Make the place-holder replacements on a line.
     *
     * @param  string  $line
     * @param  array   $replace
     * @return string
     */
    protected function makeReplacements($line, array $replace)
    {
        foreach ($replace as $key => $value) {
            $line = str_replace(':' .$key, $value, $line);
        }

        return $line;
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string  $key
     * @param  int     $number
     * @param  array   $replace
     * @param  string  $locale
     * @return string
     */
    public function choice($key, $number, array $replace = array(), $locale = null)
    {
        $line = $this->get($key, $replace, $locale = $locale ?: $this->locale);

        $replace['count'] = $number;

        return $this->makeReplacements($this->getSelector()->choose($line, $number, $locale), $replace);
    }

    /**
     * Get the translation for a given key.
     *
     * @param  string  $id
     * @param  array   $parameters
     * @param  string  $domain
     * @param  string  $locale
     * @return string
     */
    public function trans($id, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        return $this->get($id, $parameters, $locale);
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string  $id
     * @param  int     $number
     * @param  array   $parameters
     * @param  string  $domain
     * @param  string  $locale
     * @return string
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        return $this->choice($id, $number, $parameters, $locale);
    }

    /**
     * Load the specified language group.
     *
     * @param  string  $namespace
     * @param  string  $group
     * @param  string  $locale
     * @return void
     */
    public function load($group, $locale)
    {
        if (! $this->isLoaded($group, $locale)) {
            $lines = $this->loadPath($this->path, $locale, $group);

            $this->loaded[$group][$locale] = $lines;
        }
    }

    /**
     * Load a locale from a given path.
     *
     * @param  string  $path
     * @param  string  $locale
     * @param  string  $group
     * @return array
     */
    protected function loadPath($path, $locale, $group)
    {
        $filePath = $path .DS .strtoupper($locale) .DS .ucfirst($group) .'.php';

        if ($this->files->exists($filePath)) {
            return $this->files->getRequire($filePath);
        }

        return array();
    }

    /**
     * Determine if the given group has been loaded.
     *
     * @param  string  $group
     * @param  string  $locale
     * @return bool
     */
    protected function isLoaded( $group, $locale)
    {
        return isset($this->loaded[$group][$locale]);
    }

    /**
     * Get the array of locales to be checked.
     *
     * @return array
     */
    protected function parseLocale($locale)
    {
        if (! is_null($locale)) {
            return array_filter(array($locale, $this->fallback));
        } else {
            return array_filter(array($this->locale, $this->fallback));
        }
    }

    /**
     * Get the message selector instance.
     *
     * @return \Symfony\Component\Translation\MessageSelector
     */
    public function getSelector()
    {
        if (! isset($this->selector)) {
            return $this->selector = new MessageSelector();
        }

        return $this->selector;
    }

    /**
     * Set the message selector instance.
     *
     * @param  \Symfony\Component\Translation\MessageSelector  $selector
     * @return void
     */
    public function setSelector(MessageSelector $selector)
    {
        $this->selector = $selector;
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function locale()
    {
        return $this->getLocale();
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the default locale.
     *
     * @param  string  $locale
     * @return void
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Get the fallback locale being used.
     *
     * @return string
     */
    public function getFallback()
    {
        return $this->fallback;
    }

    /**
     * Set the fallback locale being used.
     *
     * @param  string  $fallback
     * @return void
     */
    public function setFallback($fallback)
    {
        $this->fallback = $fallback;
    }

}
