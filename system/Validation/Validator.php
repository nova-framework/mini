<?php

namespace System\Validation;

use System\Container\Container;
use System\Support\MessageBag;
use System\Support\Str;
use System\Translation\Translator;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Closure;
use DateTime;


class Validator
{
    /**
     * The Translator instance.
     *
     * @var \System\Translation\Translator
     */
    protected $translator;

    /**
     * The Database Presence Verifier instance.
     *
     * @var \System\Validation\DatabasePresenceVerifier
     */
    protected $presenceVerifier;

    /**
     * The Database Message Formatter instance.
     *
     * @var \System\Validation\MessageFormatter
     */
    protected $messageFormatter;

    /**
     * The message bag instance.
     *
     * @var \System\Support\MessageBag
     */
    protected $messages;

    /**
     * The data under validation.
     *
     * @var array
     */
    protected $data;

    /**
     * The files under validation.
     *
     * @var array
     */
    protected $files = array();

    /**
     * The rules to be applied to the data.
     *
     * @var array
     */
    protected $rules;

    /**
     * The array of custom error messages.
     *
     * @var array
     */
    protected $customMessages = array();

    /**
     * The array of fallback error messages.
     *
     * @var array
     */
    protected $fallbackMessages = array();

    /**
     * The array of custom attribute names.
     *
     * @var array
     */
    protected $customAttributes = array();

    /**
     * All of the custom validator extensions.
     *
     * @var array
     */
    protected $extensions = array();

    /**
     * All of the custom replacer extensions.
     *
     * @var array
     */
    protected $replacers = array();

    /**
     * The size related validation rules.
     *
     * @var array
     */
    protected $sizeRules = array('Size', 'Between', 'Min', 'Max');

    /**
     * The numeric related validation rules.
     *
     * @var array
     */
    protected $numericRules = array('Numeric', 'Integer');

    /**
     * The validation rules that imply the field is required.
     *
     * @var array
     */
    protected $implicitRules = array(
        'Required', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll', 'RequiredIf', 'Accepted'
    );


    /**
     * Create a new Validator instance.
     *
     * @param  \System\Translation\Translator  $translator
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array $customAttributes
     * @return void
     */
    public function __construct(Translator $translator, $data, $rules, $messages = array(), $customAttributes = array())
    {
        $this->translator = $translator;
        $this->customMessages = $messages;

        $this->data  = $this->parseData($data);
        $this->rules = $this->explodeRules($rules);

        $this->customAttributes = $customAttributes;

        //
        $this->messageFormatter = new MessageFormatter($this);
    }

    /**
     * Parse the data and hydrate the files array.
     *
     * @param  array  $data
     * @return array
     */
    protected function parseData(array $data)
    {
        $this->files = array();

        foreach ($data as $key => $value) {
            if ($value instanceof File) {
                $this->files[$key] = $value;

                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Explode the rules into an array of rules.
     *
     * @param  string|array  $rules
     * @return array
     */
    protected function explodeRules($rules)
    {
        foreach ($rules as $key => &$rule) {
            $rule = is_string($rule) ? explode('|', $rule) : $rule;
        }

        return $rules;
    }

    /**
     * Add conditions to a given field based on a Closure.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @param  callable  $callback
     * @return void
     */
    public function sometimes($attribute, $rules, $callback)
    {
        $payload = array_merge($this->data, $this->files);

        if (call_user_func($callback, $payload)) {
            foreach ((array) $attribute as $key) {
                $this->mergeRules($key, $rules);
            }
        }
    }

    /**
     * Merge additional rules into a given attribute.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @return void
     */
    public function mergeRules($attribute, $rules)
    {
        $current = array_get($this->rules, $attribute, array());

        if (! is_array($rules)) {
            $rules = array($rules);
        }

        $this->rules[$attribute] = array_merge($current, $this->explodeRules($rules));
    }

    /**
     * Determine if the data passes the validation rules.
     *
     * @return bool
     */
    public function passes()
    {
        $this->messages = new MessageBag();

        foreach ($this->rules as $attribute => $rules) {
            foreach ($rules as $rule) {
                $this->validate($attribute, $rule);
            }
        }

        return $this->messages->isEmpty();
    }

    /**
     * Determine if the data fails the validation rules.
     *
     * @return bool
     */
    public function fails()
    {
        return ! $this->passes();
    }

    /**
     * Validate a given attribute against a rule.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return void
     */
    protected function validate($attribute, $rule)
    {
        if (empty($rule = trim($rule))) {
            return;
        }

        list ($rule, $parameters) = $this->parseRule($rule);

        $validatable = $this->isValidatable($rule, $attribute, $value = $this->getValue($attribute));

        if ($validatable && ! $this->validateAttribute($attribute, $rule, $value, $parameters)) {
            $message = $this->getMessage($attribute, $rule);

            $this->messages->add(
                $attribute, $this->makeReplacements($message, $attribute, $rule, $parameters)
            );
        }
    }

    /**
     * Validate a given attribute against a rule.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateAttribute($attribute, $rule, $value, $parameters)
    {
        $method = "validate{$rule}";

        return call_user_func(array($this, $method), $attribute, $value, $parameters, $this);
    }

    /**
     * Format a validation error message.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function makeReplacements($message, $attribute, $rule, $parameters)
    {
        $lowerRule = Str::snake($rule);

        $message = str_replace(':attribute', $this->getAttribute($attribute), $message);

        if (! is_null($callback = array_get($this->replacers, $lowerRule))) {
            return $this->callReplacer($callback, $message, $attribute, $lowerRule, $parameters);
        }

        return $this->messageFormatter->format($message, $attribute, $rule, $parameters);
    }

    /**
     * Get the value of a given attribute.
     *
     * @param  string  $attribute
     * @return mixed
     */
    protected function getValue($attribute)
    {
        if (! is_null($value = array_get($this->data, $attribute))) {
            return $value;
        } else if (! is_null($value = array_get($this->files, $attribute))) {
            return $value;
        }
    }

    /**
     * Determine if the attribute is validatable.
     *
     * @param  string  $rule
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function isValidatable($rule, $attribute, $value)
    {
        if ($this->validateRequired($attribute, $value) || in_array($rule, $this->implicitRules)) {
            if (! $this->hasRule($attribute, array('Sometimes'))) {
                return true;
            }

            return array_key_exists($attribute, $this->data) || array_key_exists($attribute, $this->files);
        }

        return false;
    }

    /**
     * "Validate" optional attributes.
     *
     * Always returns true, just lets us put sometimes in rules.
     *
     * @return bool
     */
    protected function validateSometimes()
    {
        return true;
    }

    /**
     * Validate that a required attribute exists.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateRequired($attribute, $value)
    {
        if (is_null($value)) {
            return false;
        } else if (is_string($value) && empty(trim($value))) {
            return false;
        } else if ($value instanceof File) {
            $path = (string) $value->getPath();

            return empty($path);
        }

        return true;
    }

    /**
     * Validate the given attribute is filled if it is present.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateFilled($attribute, $value)
    {
        if (array_key_exists($attribute, $this->data) || array_key_exists($attribute, $this->files)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Determine if any of the given attributes fail the required test.
     *
     * @param  array  $attributes
     * @return bool
     */
    protected function anyFailingRequired(array $attributes)
    {
        foreach ($attributes as $key) {
            if (! $this->validateRequired($key, $this->getValue($key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if all of the given attributes fail the required test.
     *
     * @param  array  $attributes
     * @return bool
     */
    protected function allFailingRequired(array $attributes)
    {
        foreach ($attributes as $key) {
            if ($this->validateRequired($key, $this->getValue($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that an attribute exists when any other attribute exists.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWith($attribute, $value, $parameters)
    {
        if (! $this->allFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute exists when all other attributes exists.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWithAll($attribute, $value, $parameters)
    {
        if (! $this->anyFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute exists when another attribute does not.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWithout($attribute, $value, $parameters)
    {
        if ($this->anyFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute exists when all other attributes do not.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWithoutAll($attribute, $value, $parameters)
    {
        if ($this->allFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute exists when another attribute has a given value.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredIf($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'required_if');

        if ($parameters[1] == array_get($this->data, $parameters[0])) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute has a matching confirmation.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateConfirmed($attribute, $value)
    {
        return $this->validateSame($attribute, $value, array($attribute .'_confirmation'));
    }

    /**
     * Validate that two attributes match.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateSame($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'same');

        $other = array_get($this->data, $parameters[0]);

        return ! is_null($other) && ($value == $other);
    }

    /**
     * Validate that an attribute is different from another attribute.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateDifferent($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'different');

        $other = array_get($this->data, $parameters[0]);

        return ! is_null($other) && ($value != $other);
    }

    /**
     * Validate that an attribute was "accepted".
     *
     * This validation rule implies the attribute is "required".
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAccepted($attribute, $value)
    {
        $acceptable = array('yes', 'on', '1', 1, true, 'true');

        return $this->validateRequired($attribute, $value) && in_array($value, $acceptable, true);
    }

    /**
     * Validate that an attribute is an array.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateArray($attribute, $value)
    {
        return is_array($value);
    }

    /**
     * Validate that an attribute is numeric.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateNumeric($attribute, $value)
    {
        return is_numeric($value);
    }

    /**
     * Validate that an attribute is an integer.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateInteger($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate the size of an attribute.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateSize($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'size');

        return $this->getSize($attribute, $value) == $parameters[0];
    }

    /**
     * Validate the size of an attribute is between a set of values.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateBetween($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'between');

        $size = $this->getSize($attribute, $value);

        return ($size >= $parameters[0]) && ($size <= $parameters[1]);
    }

    /**
     * Validate the size of an attribute is greater than a minimum value.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateMin($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'min');

        return $this->getSize($attribute, $value) >= $parameters[0];
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateMax($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'max');

        if (($value instanceof UploadedFile) && ! $value->isValid()) {
            return false;
        }

        return $this->getSize($attribute, $value) <= $parameters[0];
    }

    /**
     * Get the size of an attribute.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return mixed
     */
    protected function getSize($attribute, $value)
    {
        if (is_numeric($value) && $this->hasRule($attribute, $this->numericRules)) {
            return array_get($this->data, $attribute);
        }

        //
        else if (is_array($value)) {
            return count($value);
        } else if ($value instanceof File) {
            return $value->getSize() / 1024;
        }

        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    /**
     * Validate an attribute is contained within a list of values.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateIn($attribute, $value, $parameters)
    {
        return in_array((string) $value, $parameters);
    }

    /**
     * Validate an attribute is not contained within a list of values.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateNotIn($attribute, $value, $parameters)
    {
        return ! in_array((string) $value, $parameters);
    }

    /**
     * Validate the uniqueness of an attribute value on a given database table.
     *
     * If a database column is not specified, the attribute will be used.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateUnique($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'unique');

        $table = $parameters[0];

        $column = isset($parameters[1]) ? $parameters[1] : $attribute;

        // Get the excluded ID column and value for the unique rule.
        $idColumn = $id = null;

        if (isset($parameters[2])) {
            $id = $parameters[2];

            if (strtolower($id) === 'null') {
                $id = null;
            }

            $idColumn = isset($parameters[3]) ? $parameters[3] : 'id';
        }

        if (isset($parameters[4])) {
            $extra = $this->getExtraConditions(array_slice($parameters, 4));
        } else {
            $extra = array();
        }

        $verifier = $this->getPresenceVerifier();

        return $verifier->getCount($table, $column, $value, $id, $idColumn, $extra) == 0;
    }

    /**
     * Validate the existence of an attribute value in a database table.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateExists($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'exists');

        $table = $parameters[0];

        $column = isset($parameters[1]) ? $parameters[1] : $attribute;

        $expected = is_array($value) ? count($value) : 1;

        return $this->getExistCount($table, $column, $value, $parameters) >= $expected;
    }

    /**
     * Get the number of records that exist in storage.
     *
     * @param  string  $table
     * @param  string  $column
     * @param  mixed   $value
     * @param  array   $parameters
     * @return int
     */
    protected function getExistCount($table, $column, $value, $parameters)
    {
        $verifier = $this->getPresenceVerifier();

        $extra = $this->getExtraConditions(array_slice($parameters, 2));

        if (is_array($value)) {
            return $verifier->getMultiCount($table, $column, $value, $extra);
        }

        return $verifier->getCount($table, $column, $value, null, null, $extra);
    }

    /**
     * Get the extra conditions for a unique / exists rule.
     *
     * @param  array  $segments
     * @return array
     */
    protected function getExtraConditions(array $segments)
    {
        $extra = array();

        $segments = array_values($segments);

        while (! empty($segments)) {
            $key = array_shift($segments);

            $extra[$key] = array_shift($segments);
        }

        return $extra;
    }

    /**
     * Validate that an attribute is a valid IP.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateIp($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate that an attribute is a valid e-mail address.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateEmail($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate that an attribute is a valid URL.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateUrl($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate the MIME type of a file upload attribute is in a set of MIME types.
     *
     * @param  string  $attribute
     * @param  array   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateMimes($attribute, $value, $parameters)
    {
        if (($value instanceof File) && ! empty($value->getPath())) {
            return in_array($value->guessExtension(), $parameters);
        }

        return true;
    }

    /**
     * Validate that an attribute contains only alphabetic characters.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAlpha($attribute, $value)
    {
        return preg_match('/^\pL+$/u', $value);
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAlphaNum($attribute, $value)
    {
        return preg_match('/^[\pL\pN]+$/u', $value);
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters, dashes, and underscores.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAlphaDash($attribute, $value)
    {
        return preg_match('/^[\pL\pN_-]+$/u', $value);
    }

    /**
     * Validate that an attribute passes a regular expression check.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateRegex($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'regex');

        return preg_match($parameters[0], $value);
    }

    /**
     * Validate that an attribute is a valid date.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateDate($attribute, $value)
    {
        if ($value instanceof DateTime) {
            return true;
        } else if (strtotime($value) === false) {
            return false;
        }

        $date = date_parse($value);

        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /**
     * Validate that an attribute matches a date format.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateDateFormat($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'date_format');

        $parsed = date_parse_from_format($parameters[0], $value);

        return ($parsed['error_count'] === 0) && ($parsed['warning_count'] === 0);
    }

    /**
     * Validate the date is before a given date.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateBefore($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'before');

        $date = strtotime($other = $parameters[0]);

        if ($date === false) {
            $date = strtotime($this->getValue($other));
        }

        return strtotime($value) < $date;
    }

    /**
     * Validate the date is after a given date.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateAfter($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'after');

        $date = strtotime($other = $parameters[0]);

        if ($date === false) {
            $date = strtotime($this->getValue($other));
        }

        return strtotime($value) > $date;
    }

    /**
     * Validate that an attribute is a valid timezone.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateTimezone($attribute, $value)
    {
        try {
            new \DateTimeZone($value);
        }
        catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Validate that an attribute is a boolean.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateBoolean($attribute, $value)
    {
        $acceptable = array(true, false, 0, 1, '0', '1');

        return in_array($value, $acceptable, true);
    }

    /**
     * Get the validation message for an attribute and rule.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return string
     */
    protected function getMessage($attribute, $rule)
    {
        $lowerRule = Str::snake($rule);

        if (! is_null($message = $this->getInlineMessage($attribute, $lowerRule, $this->customMessages))) {
            return $message;
        }

        $ruleKey = lcfirst($rule);

        //
        $message = $this->translator->trans($key = "validation.custom.{$attribute}.{$ruleKey}");

        if ($message !== $key) {
            return $message;
        } else if (in_array($rule, $this->sizeRules)) {
            return $this->getSizeMessage($attribute, $ruleKey);
        }

        $message = $this->translator->trans($key = "validation.{$ruleKey}");

        if ($message !== $key) {
            return $message;
        }

        //
        else if (! is_null($message = $this->getInlineMessage($attribute, $lowerRule, $this->fallbackMessages))) {
            return $message;
        }

        return $key;
    }

    /**
     * Get the inline message for a rule if it exists.
     *
     * @param  string  $attribute
     * @param  string  $lowerRule
     * @param  array   $source
     * @return string
     */
    protected function getInlineMessage($attribute, $lowerRule, $source)
    {
        $keys = array("{$attribute}.{$lowerRule}", $lowerRule);

        foreach ($keys as $key) {
            if (isset($source[$key])) {
                return $source[$key];
            }
        }
    }

    /**
     * Get the proper error message for an attribute and size rule.
     *
     * @param  string  $attribute
     * @param  string  $ruleKey
     * @return string
     */
    protected function getSizeMessage($attribute, $ruleKey)
    {
        if ($this->hasRule($attribute, $this->numericRules)) {
            $type = 'numeric';
        } else if ($this->hasRule($attribute, array('Array'))) {
            $type = 'array';
        } else if (array_key_exists($attribute, $this->files)) {
            $type = 'file';
        } else {
            $type = 'string';
        }

        return $this->translator->trans("validation.{$ruleKey}.{$type}");
    }

    /**
     * Get the displayable name of the attribute.
     *
     * @param  string  $attribute
     * @return string
     */
    public function getAttribute($attribute)
    {
        if (isset($this->customAttributes[$attribute])) {
            return $this->customAttributes[$attribute];
        }

        $message = $this->translator->trans($key = "validation.attributes.{$attribute}");

        if ($message !== $key) {
            return $message;
        }

        return str_replace('_', ' ', $attribute);
    }

    /**
     * Determine if the given attribute has a rule in the given set.
     *
     * @param  string  $attribute
     * @param  array   $rules
     * @return bool
     */
    protected function hasRule($attribute, $rules)
    {
        $rules = (array) $rules;

        foreach ($this->rules[$attribute] as $rule) {
            list ($rule, $parameters) = $this->parseRule($rule);

            if (in_array($rule, $rules)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the rule name and parameters from a rule.
     *
     * @param  string  $rule
     * @return array
     */
    protected function parseRule($rule)
    {
        $parameters = array();

        if (strpos($rule, ':') !== false) {
            list ($rule, $parameter) = explode(':', $rule, 2);

            if (strtolower($rule) !== 'regex') {
                $parameters = str_getcsv($parameter);
            } else {
                $parameters = array($parameter);
            }
        }

        $rule = Str::studly($rule);

        return array($rule, $parameters);
    }

    /**
     * Get the array of custom validator extensions.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Register an array of custom validator extensions.
     *
     * @param  array  $extensions
     * @return void
     */
    public function addExtensions(array $extensions)
    {
        if (empty($extensions)) {
            return;
        }

        $keys = array_map(function ($value)
        {
            return Str::snake($value);

        }, array_keys($extensions));

        $this->extensions = array_merge(
            $this->extensions, array_combine($keys, array_values($extensions))
        );
    }

    /**
     * Register an array of custom implicit validator extensions.
     *
     * @param  array  $extensions
     * @return void
     */
    public function addImplicitExtensions(array $extensions)
    {
        $this->addExtensions($extensions);

        $keys = array_keys($extensions);

        foreach ($keys as $rule) {
            $this->implicitRules[] = Str::studly($rule);
        }
    }

    /**
     * Register a custom validator extension.
     *
     * @param  string  $rule
     * @param  \Closure|string  $extension
     * @return void
     */
    public function addExtension($rule, $extension)
    {
        $lowerRule = Str::snake($rule);

        $this->extensions[$lowerRule] = $extension;
    }

    /**
     * Register a custom implicit validator extension.
     *
     * @param  string   $rule
     * @param  \Closure|string  $extension
     * @return void
     */
    public function addImplicitExtension($rule, $extension)
    {
        $this->addExtension($rule, $extension);

        $this->implicitRules[] = Str::studly($rule);
    }

    /**
     * Get the array of custom validator message replacers.
     *
     * @return array
     */
    public function getReplacers()
    {
        return $this->replacers;
    }

    /**
     * Register an array of custom validator message replacers.
     *
     * @param  array  $replacers
     * @return void
     */
    public function addReplacers(array $replacers)
    {
        if (empty($replacers)) {
            return;
        }

        $keys = array_map(function ($value)
        {
            return Str::snake($value);

        }, array_keys($replacers));

        $this->replacers = array_merge(
            $this->replacers, array_combine($keys, array_values($replacers))
        );
    }

    /**
     * Register a custom validator message replacer.
     *
     * @param  string  $rule
     * @param  \Closure|string  $replacer
     * @return void
     */
    public function addReplacer($rule, $replacer)
    {
        $lowerRule = Str::snake($rule);

        $this->replacers[$lowerRule] = $replacer;
    }

    /**
     * Get the data under validation.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return void
     */
    public function setData(array $data)
    {
        $this->data = $this->parseData($data);
    }

    /**
     * Get the validation rules.
     *
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Set the validation rules.
     *
     * @param  array  $rules
     * @return \System\Validation\Validator
     */
    public function setRules(array $rules)
    {
        $this->rules = $this->explodeRules($rules);

        return $this;
    }

    /**
     * Set the custom attributes on the validator.
     *
     * @param  array  $attributes
     * @return \System\Validation\Validator
     */
    public function setAttributeNames(array $attributes)
    {
        $this->customAttributes = $attributes;

        return $this;
    }

    /**
     * Get the files under validation.
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Set the files under validation.
     *
     * @param  array  $files
     * @return \System\Validation\Validator
     */
    public function setFiles(array $files)
    {
        $this->files = $files;

        return $this;
    }

    /**
     * Get the Database Presence Verifier implementation.
     *
     * @return \System\Validation\DatabasePresenceVerifier
     *
     * @throws \RuntimeException
     */
    public function getPresenceVerifier()
    {
        if (! isset($this->presenceVerifier)) {
            throw new \RuntimeException("Presence verifier has not been set.");
        }

        return $this->presenceVerifier;
    }

    /**
     * Set the Database Presence Verifier implementation.
     *
     * @param  \System\Validation\DatabasePresenceVerifier  $presenceVerifier
     * @return void
     */
    public function setPresenceVerifier(DatabasePresenceVerifier $presenceVerifier)
    {
        $this->presenceVerifier = $presenceVerifier;
    }

    /**
     * Get the Translator implementation.
     *
     * @return \System\Translation\Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Set the Translator implementation.
     *
     * @param  \System\Translation\Translator  $translator
     * @return void
     */
    public function setTranslator(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Get the custom messages for the validator
     *
     * @return array
     */
    public function getCustomMessages()
    {
        return $this->customMessages;
    }

    /**
     * Set the custom messages for the validator
     *
     * @param  array  $messages
     * @return void
     */
    public function setCustomMessages(array $messages)
    {
        $this->customMessages = array_merge($this->customMessages, $messages);
    }

    /**
     * Get the fallback messages for the validator.
     *
     * @return array
     */
    public function getFallbackMessages()
    {
        return $this->fallbackMessages;
    }

    /**
     * Set the fallback messages for the validator.
     *
     * @param  array  $messages
     * @return void
     */
    public function setFallbackMessages(array $messages)
    {
        $this->fallbackMessages = $messages;
    }

    /**
     * Get the message container for the validator.
     *
     * @return \System\Support\MessageBag
     */
    public function messages()
    {
        if (! isset($this->messages)) {
            $this->passes();
        }

        return $this->messages;
    }

    /**
     * An alternative more semantic shortcut to the message container.
     *
     * @return \System\Support\MessageBag
     */
    public function errors()
    {
        return $this->messages();
    }

    /**
     * Get the messages for the instance.
     *
     * @return \System\Support\MessageBag
     */
    public function getMessageBag()
    {
        return $this->messages();
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \System\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Call a custom validator extension.
     *
     * @param  mixed  $callback
     * @param  array  $parameters
     * @return bool
     */
    protected function callExtension($callback, $parameters)
    {
        if (is_string($callback)) {
            list ($class, $method) = explode('@', $callback);

            $instance = $this->container->make($class);

            $callback = array($instance, $method);
        }

        return call_user_func_array($callback, $parameters);
    }

    /**
     * Call a custom validator message replacer.
     *
     * @param  mixed  $callback
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function callReplacer($callback, $message, $attribute, $rule, $parameters)
    {
        if (is_string($callback)) {
            list ($class, $method) = explode('@', $callback);

            $instance = $this->container->make($class);

            $callback = array($instance, $method);
        }

        return call_user_func($callback, $message, $attribute, $rule, $parameters);
    }

    /**
     * Require a certain number of parameters to be present.
     *
     * @param  int    $count
     * @param  array  $parameters
     * @param  string $rule
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function requireParameterCount($count, $parameters, $rule)
    {
        if (count($parameters) < $count) {
            throw new \InvalidArgumentException("Validation rule {$rule} requires at least {$count} parameters.");
        }
    }

    /**
     * Handle dynamic calls to class methods.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        $rule = Str::snake(substr($method, 8));

        if (! is_null($callback = array_get($this->extensions, $rule))) {
            return $this->callExtension($callback, $parameters);
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist.");
    }
}
