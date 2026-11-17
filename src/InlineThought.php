<?php
declare(strict_types=1);

/**
 * Deler modellens strøm i resonnement og svar. Søren skriver tankene sine selv, på norsk, inne i
 * <tanker>...</tanker> før svaret, i samme kall. Det er raskere enn innebygd resonnement (DeepSeek-R1),
 * som tenkte lenge og på engelsk, og vi slipper et ekstra kall for å oversette.
 *
 * Sikring mot tankeløkker: blir resonnementet lengre enn $maxThought tegn, kastes TooLong.
 * Taggen kan byttes, så samme klasse også skiller ut den korte forklaringen <kort> i produksjon.
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
                    return; // kan fortsatt bli taggen
                } else {
                    $this->state = 'answer'; // modellen hoppet over tankene
                }
            } elseif ($this->state === 'think') {
                $close = "</{$this->tag}>";
                if (($p = strpos($this->buf, $close)) !== false) {
                    $this->think(substr($this->buf, 0, $p));
                    $this->buf = ltrim(substr($this->buf, $p + strlen($close)));
                    $this->state = 'answer';
                } elseif (mb_strlen($this->buf) > strlen($close)) {
                    // hold igjen i tilfelle taggen er delt, og tell tegn så æøå ikke deles i to
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

/** Kastes når Søren bruker for lang tid eller tenker for lenge. */
final class TooLong extends RuntimeException {}
