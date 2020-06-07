<?php
namespace Misuzu\Users\Assets;

use InvalidArgumentException;
use Misuzu\Config;
use Misuzu\Users\User;

// attachment and attributes are to be stored in the same byte
// left half is for attributes, right half is for attachments
// this makes for 16 possible attachments and 4 possible attributes
// since attachments are just an incrementing number and attrs are flags

class UserBackgroundAsset extends UserImageAsset {
    private const FORMAT = 'backgrounds/original/%d.msz';

    private const MAX_WIDTH  = 3840;
    private const MAX_HEIGHT = 2160;
    private const MAX_BYTES  = 1048576;

    public const ATTACH_NONE    = 0x00;
    public const ATTACH_COVER   = 0x01;
    public const ATTACH_STRETCH = 0x02;
    public const ATTACH_TILE    = 0x03;
    public const ATTACH_CONTAIN = 0x04;

    public const ATTRIB_BLEND = 0x10;
    public const ATTRIB_SLIDE = 0x20;

    public const ATTACHMENT_STRINGS = [
        self::ATTACH_COVER   => 'cover',
        self::ATTACH_STRETCH => 'stretch',
        self::ATTACH_TILE    => 'tile',
        self::ATTACH_CONTAIN => 'contain',
    ];

    public const ATTRIBUTE_STRINGS = [
        self::ATTRIB_BLEND => 'blend',
        self::ATTRIB_SLIDE => 'slide',
    ];

    public static function getAttachmentStringOptions(): array {
        return [
            self::ATTACH_COVER   => 'Cover',
            self::ATTACH_STRETCH => 'Stretch',
            self::ATTACH_TILE    => 'Tile',
            self::ATTACH_CONTAIN => 'Contain',
        ];
    }

    public function getMaxWidth(): int {
        return Config::get('background.max_width', Config::TYPE_INT, self::MAX_WIDTH);
    }
    public function getMaxHeight(): int {
        return Config::get('background.max_height', Config::TYPE_INT, self::MAX_HEIGHT);
    }
    public function getMaxBytes(): int {
        return Config::get('background.max_size', Config::TYPE_INT, self::MAX_BYTES);
    }

    public function getUrl(): string {
        return url('user-background', ['user' => $this->getUser()->getId()]);
    }

    public function getFileName(): string {
        return sprintf('background-%1$d.%2$s', $this->getUser()->getId(), $this->getFileExtension());
    }

    public function getRelativePath(): string {
        return sprintf(self::FORMAT, $this->getUser()->getId());
    }

    public function getAttachment(): int {
        return $this->getUser()->getBackgroundSettings() & 0x0F;
    }
    public function getAttachmentString(): string {
        return self::ATTACHMENT_STRINGS[$this->getAttachment()] ?? '';
    }
    public function setAttachment(int $attach): self {
        $this->getUser()->setBackgroundSettings($this->getAttributes() | ($attach & 0x0F));
        return $this;
    }
    public function setAttachmentString(string $attach): self {
        if(!in_array($attach, self::ATTACHMENT_STRINGS))
            throw new InvalidArgumentException;
        $this->setAttachment(array_flip(self::ATTACHMENT_STRINGS)[$attach]);
        return $this;
    }

    public function getAttributes(): int {
        return $this->getUser()->getBackgroundSettings() & 0xF0;
    }
    public function setAttributes(int $attrib): self {
        $this->getUser()->setBackgroundSettings($this->getAttachment() | ($attrib & 0xF0));
        return $this;
    }
    public function isBlend(): bool {
        return $this->getAttributes() & self::ATTRIB_BLEND;
    }
    public function setBlend(bool $blend): self {
        $this->getUser()->setBackgroundSettings(
            $blend
            ? ($this->getUser()->getBackgroundSettings() |  self::ATTRIB_BLEND)
            : ($this->getUser()->getBackgroundSettings() & ~self::ATTRIB_BLEND)
        );
        return $this;
    }
    public function isSlide(): bool {
        return $this->getAttributes() & self::ATTRIB_SLIDE;
    }
    public function setSlide(bool $slide): self {
        $this->getUser()->setBackgroundSettings(
            $slide
            ? ($this->getUser()->getBackgroundSettings() |  self::ATTRIB_SLIDE)
            : ($this->getUser()->getBackgroundSettings() & ~self::ATTRIB_SLIDE)
        );
        return $this;
    }

    public function getClassNames(string $format = '%s'): array {
        $names = [];
        $attachment = $this->getAttachment();
        $attributes = $this->getAttributes();

        if(array_key_exists($attachment, self::ATTACHMENT_STRINGS))
            $names[] = sprintf($format, self::ATTACHMENT_STRINGS[$attachment]);

        foreach(self::ATTRIBUTE_STRINGS as $flag => $name)
            if(($attributes & $flag) > 0)
                $names[] = sprintf($format, $name);

        return $names;
    }

    public function delete(): void {
        parent::delete();
        $this->getUser()->setBackgroundSettings(0);
    }

    public function jsonSerialize() {
        return array_merge(parent::jsonSerialize(), [
            'attachment' => $this->getAttachmentString(),
            'is_blend' => $this->isBlend(),
            'is_slide' => $this->isSlide(),
        ]);
    }
}
