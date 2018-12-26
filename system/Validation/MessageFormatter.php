<?php

namespace System\Validation;


class MessageFormatter
{
    /**
     * The Validator instance.
     *
     * @var \System\Validation\Validator
     */
    protected $validator;


    /**
     * Create a new Message Formatter instance.
     *
     * @param  $validaror  \System\Validation\Validator
     * @return void
     */
    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Replace all place-holders for the given rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    public function format($message, $attribute, $rule, $parameters)
    {
        if (! method_exists($this, $method = "replace{$rule}")) {
            return $message;
        }

        return call_user_func(array($this, $method), $message, $attribute, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the between rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceBetween($message, $attribute, $rule, $parameters)
    {
        return str_replace(array(':min', ':max'), $parameters, $message);
    }

    /**
     * Replace all place-holders for the size rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceSize($message, $attribute, $rule, $parameters)
    {
        return str_replace(':size', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the min rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceMin($message, $attribute, $rule, $parameters)
    {
        return str_replace(':min', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the max rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceMax($message, $attribute, $rule, $parameters)
    {
        return str_replace(':max', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the in rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceIn($message, $attribute, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the not_in rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceNotIn($message, $attribute, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the mimes rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceMimes($message, $attribute, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the required_with rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredWith($message, $attribute, $rule, $parameters)
    {
        $values = implode(' / ', $this->getAttributeList($parameters));

        return str_replace(':values', $values, $message);
    }

    /**
     * Replace all place-holders for the required_without rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredWithout($message, $attribute, $rule, $parameters)
    {
        $values = implode(' / ', $this->getAttributeList($parameters));

        return str_replace(':values', $values, $message);
    }

    /**
     * Replace all place-holders for the required_without_all rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredWithoutAll($message, $attribute, $rule, $parameters)
    {
        $values = implode(' / ', $this->getAttributeList($parameters));

        return str_replace(':values', $values, $message);
    }

    /**
     * Replace all place-holders for the required_if rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredIf($message, $attribute, $rule, $parameters)
    {
        $parameters[0] = $this->getAttribute($parameters[0]);

        return str_replace(array(':other', ':value'), $parameters, $message);
    }

    /**
     * Replace all place-holders for the same rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceSame($message, $attribute, $rule, $parameters)
    {
        return str_replace(':other', $this->getAttribute($parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the different rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceDifferent($message, $attribute, $rule, $parameters)
    {
        return str_replace(':other', $this->getAttribute($parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the date_format rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceDateFormat($message, $attribute, $rule, $parameters)
    {
        return str_replace(':format', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the before rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceBefore($message, $attribute, $rule, $parameters)
    {
        if (! strtotime($value = $parameters[0])) {
            $value = $this->getAttribute($value);
        }

        return str_replace(':date', $value, $message);
    }

    /**
     * Replace all place-holders for the after rule.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceAfter($message, $attribute, $rule, $parameters)
    {
        if (! strtotime($value = $parameters[0])) {
            $value = $this->getAttribute($value);
        }

        return str_replace(':date', $value, $message);
    }

    /**
     * Transform an array of attributes to their displayable form.
     *
     * @param  array  $values
     * @return array
     */
    protected function getAttributeList(array $values)
    {
        $attributes = array();

        foreach ($values as $key => $value) {
            $attributes[$key] = $this->getAttribute($value);
        }

        return $attributes;
    }

    /**
     * Get the displayable name of the attribute.
     *
     * @param  string  $attribute
     * @return string
     */
    protected function getAttribute($attribute)
    {
        return $this->validator->getAttribute($attribute);
    }
}
