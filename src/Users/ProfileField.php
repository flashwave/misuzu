<?php
namespace Misuzu\Users;

use Misuzu\DB;

class ProfileField {
    public static function createField(
        string $fieldKey,
        string $fieldTitle,
        string $fieldRegex,
        int $fieldOrder
    ): ?ProfileField {
        $createField = DB::prepare('
            INSERT INTO `msz_profile_fields` (
                `field_order`, `field_key`, `field_title`, `field_regex`
            ) VALUES (:order, :key, :title, :regex)
        ')->bind('order', $fieldOrder)->bind('key',   $fieldKey)
          ->bind('title', $fieldTitle)->bind('regex', $fieldRegex)
          ->executeGetId();

        if($createField < 1)
            return null;

        return static::get($createField);
    }
    public static function createFormat(
        int $fieldId,
        string $formatDisplay = '%s',
        ?string $formatLink = null,
        ?string $formatRegex = null
    ): ?ProfileField {
        $createFormat = DB::prepare('
            INSERT INTO `msz_profile_fields_formats` (
                `field_id`, `format_regex`, `format_link`, `format_display`
            ) VALUES (:field, :regex, :link, :display)
        ')->bind('field', $fieldId)   ->bind('regex',   $formatRegex)
          ->bind('link',  $formatLink)->bind('display', $formatDisplay)
          ->executeGetId();

        if($createFormat < 1)
            return null;

        return static::get($createFormat);
    }

    public static function get(int $fieldId): ?ProfileField {
        return DB::prepare(
            'SELECT `field_id`, `field_order`, `field_key`, `field_title`, `field_regex`'
            . ' FROM `msz_profile_fields`'
            . ' WHERE `field_id` = :field_id'
        )->bind('field_id', $fieldId)->fetchObject(ProfileField::class);
    }

    public static function user(int $userId, bool $filterEmpty = true): array {
        $fields = DB::prepare(
            'SELECT pf.`field_id`, pf.`field_order`, pf.`field_key`, pf.`field_title`, pf.`field_regex`'
            . ', pff.`format_id`, pff.`format_regex`, pff.`format_link`, pff.`format_display`'
            . ', COALESCE(pfv.`user_id`, :user2) AS `user_id`, pfv.`field_value`'
            . ' FROM `msz_profile_fields` AS pf'
            . ' LEFT JOIN `msz_profile_fields_values` AS pfv ON pfv.`field_id` = pf.`field_id` AND pfv.`user_id` = :user1'
            . ' LEFT JOIN `msz_profile_fields_formats` AS pff ON pff.`field_id` = pf.`field_id` AND pff.`format_id` = pfv.`format_id`'
            . ' ORDER BY pf.`field_order`'
        )->bind('user1', $userId)->bind('user2', $userId)->fetchObjects(ProfileField::class);

        if($filterEmpty) {
            $newFields = [];

            foreach($fields as $field) {
                if(!empty($field->field_value))
                    $newFields[] = $field;
            }

            $fields = $newFields;
        }

        return $fields;
    }

    public function findDisplayFormat(string $value): int {
        if(!isset($this->field_id))
            return 0;

        $format = DB::prepare('
            SELECT `format_id`
            FROM `msz_profile_fields_formats`
            WHERE `field_id` = :field
            AND `format_regex` IS NOT NULL
            AND :value REGEXP `format_regex`
        ')->bind('field', $this->field_id)
          ->bind('value', $value)
          ->fetchColumn();

        if($format < 1) {
            $format = DB::prepare('
                SELECT `format_id`
                FROM `msz_profile_fields_formats`
                WHERE `field_id` = :field
                AND `format_regex` IS NULL
            ')->bind('field', $this->field_id)
              ->fetchColumn(0, 0);
        }

        return $format;
    }

    // todo: use exceptions
    public function setFieldValue(string $value): bool {
        if(!isset($this->user_id, $this->field_id, $this->field_regex))
            return false;

        if(empty($value)) {
            DB::prepare('
                DELETE FROM `msz_profile_fields_values`
                WHERE `user_id` = :user
                AND `field_id` = :field
            ')->bind('user', $this->user_id)
              ->bind('field', $this->field_id)
              ->execute();
            $this->field_value = '';
            return true;
        }

        if(preg_match($this->field_regex, $value, $matches)) {
            $value = $matches[1];
        } else {
            return false;
        }

        $displayFormat = $this->findDisplayFormat($value);

        if($displayFormat < 1)
            return false;

        $updateField = DB::prepare('
            REPLACE INTO `msz_profile_fields_values`
                (`field_id`, `user_id`, `format_id`, `field_value`)
            VALUES
                (:field, :user, :format, :value)
        ')->bind('field', $this->field_id)
          ->bind('user', $this->user_id)
          ->bind('format', $displayFormat)
          ->bind('value', $value)
          ->execute();

        if(!$updateField)
            return false;

        $this->field_value = $value;
        return true;
    }
}
