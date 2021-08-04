<?php

declare(strict_types=1);

namespace Baraja\HtmlHeader;


/**
 * Renders the complete valid header HTML content based on MetaBuilder.
 * Simply define individual tags by calling methods and then get the entire contents of the header:
 *
 * $header = new HtmlHeader;
 * $header->title('My webpage');
 * $header->meta('description', 'Awesome page...');
 * // or shortcut:
 * $header->metaDescription('Awesome page...');
 *
 * $header->link('canonical', 'https://baraja.cz');
 * $header->link('alternate', [
 *     'hreflang' => 'cs',
 *     'href' => 'https://baraja.cz',
 * ]);
 *
 * $header->jsonld([
 *     '@context' => 'http://schema.org',
 *     '@type' => 'Person',
 *     'name' => 'Jan Barášek',
 * ]);
 */
final class HtmlHeader implements \Stringable
{
	/** @var array<int, string> */
	private array $order = ['title', 'meta', 'og', 'twitter', 'link', 'json-ld'];

	/** @var array<string, array<int|string, array<int, string>|string>> */
	private array $tags = [];

	private ?string $title = null;

	private ?string $description = null;

	private bool $automaticOpenGraph = true;


	public function __toString(): string
	{
		return $this->render();
	}


	/**
	 * Render all or a specific group of HTML meta tags.
	 *
	 * @param array<int, string> $groups render specific groups or keep empty for all records.
	 */
	public function render(?array $groups = null): string
	{
		$items = [];
		if ($this->automaticOpenGraph === true) {
			$this->computeAutomaticOpenGraph();
		}
		foreach (($groups ?? $this->order) as $group) {
			$items[] = $this->renderGroup($group);
		}

		return trim(implode('', $items));
	}


	/**
	 * @param array<int, string> $order
	 */
	public function setCustomOrderingStrategy(array $order): void
	{
		$this->order = $order;
	}


	/**
	 * Build an HTML link tag.
	 *
	 * @param array<string, string>|string|null $value
	 */
	public function link(string $key, string|array|null $value): void
	{
		if ($value === [] || $value === '') {
			return;
		}
		$attributes = ['rel' => $key];
		if (is_array($value)) {
			foreach ($value as $valueKey => $v) {
				$attributes[$valueKey] = $v;
			}
		} else {
			$attributes['href'] = $value;
		}
		$this->addToTagsGroup('link', $key, $this->createTag('link', $attributes));
	}


	public function metaDescription(string $content): void
	{
		$content = $this->normalize($content);
		if ($content === '') {
			return;
		}
		$this->description = $content;
		$this->meta('description', $content);
	}


	/**
	 * Build an HTML meta tag.
	 *
	 * @param string|array<string, string>|null $value
	 */
	public function meta(string $key, string|array|null $value): void
	{
		if ($value === [] || $value === '') {
			return;
		}
		if ($key === 'description' && is_string($value)) {
			$value = $this->truncate($value, 153);
		}
		$attributes = ['name' => $key];
		if (is_array($value)) {
			foreach ($value as $valueKey => $v) {
				$attributes[$valueKey] = $v;
			}
		} else {
			$attributes['content'] = $value;
		}
		$this->addToTagsGroup('meta', $key, $this->createTag('meta', $attributes));
	}


	/** Build an Open Graph meta tag. */
	public function og(string $key, string $value, bool $prefixed = true): void
	{
		if ($value !== '') {
			$key = $prefixed ? 'og:' . $key : $key;
			$this->addToTagsGroup('og', $key, $this->createTag('meta', [
				'property' => $key,
				'content' => $value,
			]));
		}
	}


	/**
	 * Build an JSON linked data meta tag.
	 *
	 * @param array<string, mixed> $schema
	 */
	public function jsonld(array $schema): void
	{
		if ($schema === []) {
			return;
		}

		try {
			$json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			throw new \InvalidArgumentException('Invalid json: ' . $e->getMessage(), $e->getCode(), $e);
		}
		$this->tags['json-ld'][] = '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
	}


	/** Build a Title HTML tag. */
	public function title(?string $value): void
	{
		if ($value === null) {
			return;
		}
		if (($value = $this->normalize($value)) !== '') {
			$this->title = $value;
			$this->tags['title'][] = '<title>' . htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>';
		}
	}


	/** Build a Twitter Card meta tag. */
	public function twitter(string $key, string $value, bool $prefixed = true): void
	{
		if ($value !== '') {
			$key = $prefixed ? 'twitter:' . $key : $key;
			$this->addToTagsGroup('twitter', $key, $this->createTag('meta', [
				'name' => $key,
				'content' => $value,
			]));
		}
	}


	public function setAutomaticOpenGraph(bool $automaticOpenGraph): void
	{
		$this->automaticOpenGraph = $automaticOpenGraph;
	}


	/** Render all HTML meta tags from a specific group. */
	private function renderGroup(string $group): string
	{
		if (isset($this->tags[$group]) === false) {
			return '';
		}

		$html = [];
		foreach ($this->tags[$group] as $tag) {
			if (is_array($tag)) {
				foreach ($tag as $t) {
					$html[] = $t;
				}
			} else {
				$html[] = $tag;
			}
		}

		return count($html) > 0
			? implode("\n", $html) . "\n"
			: '';
	}


	/** Add single HTML element to tags group. */
	private function addToTagsGroup(string $group, string $key, string $tag): void
	{
		if (isset($this->tags[$group][$key])) {
			if (is_array($this->tags[$group][$key])) {
				$this->tags[$group][$key][] = $tag;
			} else {
				$this->tags[$group][$key] = [$this->tags[$group][$key], $tag];
			}
		} else {
			$this->tags[$group][$key] = $tag;
		}
	}


	/**
	 * Build an HTML tag
	 *
	 * @param array<string, string|null> $attributes
	 */
	private function createTag(string $tagName, array $attributes = []): string
	{
		$escapeAttr = static function (string $s): string {
			if (str_contains($s, '`') && strpbrk($s, ' <>"\'') === false) {
				$s .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
			}

			return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', true);
		};

		$attrItems = [];
		foreach ($attributes as $key => $value) {
			if ($value === null) {
				continue;
			}
			$attrItems[] = $escapeAttr($key) . '="' . $escapeAttr($value) . '"';
		}

		return '<' . $tagName . (count($attrItems) > 0 ? ' ' . implode(' ', $attrItems) : '') . '>';
	}


	private function computeAutomaticOpenGraph(): void
	{
		if (isset($this->tags['og']['og:title']) === false && $this->title !== null) {
			$this->og('title', $this->truncate($this->title, 60));
		}
		if (isset($this->tags['og']['og:description']) === false && $this->description !== null) {
			$this->og('description', $this->truncate($this->description, 65));
		}
		if (isset($this->tags['twitter']['twitter:card']) === false) {
			$this->twitter('card', 'summary');
		}
		if (isset($this->tags['twitter']['twitter:description']) === false && $this->title !== null) {
			$this->twitter('title', $this->truncate($this->title, 55));
		}
		if (isset($this->tags['twitter']['twitter:description']) === false && $this->description !== null) {
			$this->twitter('description', $this->truncate($this->description, 50));
		}
	}


	/**
	 * Truncates a UTF-8 string to given maximal length, while trying not to split whole words.
	 * Only if the string is truncated, an ellipsis (or something else set with third argument)
	 * is appended to the string.
	 */
	private function truncate(string $s, int $maxLen, string $append = "\u{2026}"): string
	{
		if (mb_strlen($s, 'UTF-8') > $maxLen) {
			$maxLen -= mb_strlen($append, 'UTF-8');
			if ($maxLen < 1) {
				return $append;
			}
			if (preg_match('#^.{1,' . $maxLen . '}(?=[\s\x00-/:-@\[-`{-~])#us', $s, $matches) === 1) {
				return $matches[0] . $append;
			}

			return mb_substr($s, 0, $maxLen, 'UTF-8') . $append;
		}

		return $s;
	}


	private function normalize(string $haystack): string
	{
		$haystack = strip_tags($haystack);
		$haystack = (string) preg_replace('/[*\-=]{2,}/', ' ', $haystack);
		$haystack = (string) preg_replace('/\s+/', ' ', $haystack);

		return trim($haystack);
	}
}
