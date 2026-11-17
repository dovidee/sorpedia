<?php
declare(strict_types=1);

/**
 * Splits the model's stream into reasoning and answer. Søren writes his thoughts himself, in Norwegian, inside
 * <tanker>...</tanker> before the answer, in the same call. That is faster than built-in reasoning (DeepSeek-R1),
 * which thought for a long time and in English, and we avoid an extra call to translate.
 *
 * Guard against thought loops: if the reasoning gets longer than $maxThought characters, TooLong is thrown.
 * The tag can be changed, so the same class also separates out the short explanation <kort> in production.
 */
final class InlineThought
{
    private string $state = 'start';
    private string $buf = '';
    public int $thoughtLen = 0;

    public function __construct(private $onThink, private $onAnswer, private int $maxThought = 700, private string $tag = 'tanker') {}

    public function push(string $t): void
    {
        $this->buf .= $t;
        while ($this->buf !== '') {
            if ($this->state === 'start') {
                $trim = ltrim($this->buf);
                $open = "<{$this->tag}>";
                if (str_starts_with($trim, $open)) {
                    $this->buf = substr($trim, strlen($open));
                    $this->state = 'think';
                } elseif (str_starts_with($open, $trim)) {
                    return; // may still become the tag
                } else {
                    $this->state = 'answer'; // the model skipped the thoughts
                }
            } elseif ($this->state === 'think') {
                $close = "</{$this->tag}>";
                if (($p = strpos($this->buf, $close)) !== false) {
                    $this->think(substr($this->buf, 0, $p));
                    $this->buf = ltrim(substr($this->buf, $p + strlen($close)));
                    $this->state = 'answer';
                } elseif (mb_strlen($this->buf) > strlen($close)) {
                    // hold back in case the tag is split, and count characters so æøå aren't split in two
                    $emit = mb_substr($this->buf, 0, mb_strlen($this->buf) - strlen($close));
                    $this->think($emit);
                    $this->buf = substr($this->buf, strlen($emit));
                    return;
                } else {
                    return;
                }
            } else {
                ($this->onAnswer)($this->buf);
                $this->buf = '';
            }
        }
    }

    public function end(): void
    {
        if ($this->buf === '') {
            return;
        }
        $this->state === 'think' ? $this->think($this->buf) : ($this->onAnswer)($this->buf);
        $this->buf = '';
    }

    private function think(string $t): void
    {
        $this->thoughtLen += mb_strlen($t);
        if ($this->thoughtLen > $this->maxThought) {
            throw new TooLong('resonnement');
        }
        ($this->onThink)($t);
    }
}

/** Thrown when Søren takes too long or thinks for too long. */
final class TooLong extends RuntimeException {}
