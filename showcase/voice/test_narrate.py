import unittest
from types import SimpleNamespace

from narrate import length_problem, words_from_tokens


def token(text, start, end):
    return SimpleNamespace(text=text, start_ts=start, end_ts=end)


class WordsFromTokens(unittest.TestCase):
    def test_times_are_in_milliseconds_after_the_offset(self):
        words = words_from_tokens([token("This", 0.1, 0.35), token("is", 0.35, 0.5)], 400)
        self.assertEqual(words, [
            {"text": "This", "startMs": 500, "endMs": 750},
            {"text": "is", "startMs": 750, "endMs": 900},
        ])

    def test_punctuation_joins_the_word_before_it(self):
        words = words_from_tokens([token("Cartopia", 0.0, 0.6), token(",", None, None), token("an", 0.7, 0.8)], 0)
        self.assertEqual([w["text"] for w in words], ["Cartopia,", "an"])

    def test_an_untimed_word_starts_where_the_last_one_ended(self):
        words = words_from_tokens([token("one", 0.0, 0.3), token("click", None, None)], 1000)
        self.assertEqual(words[1], {"text": "click", "startMs": 1300, "endMs": 1300})


class LengthProblem(unittest.TestCase):
    def test_accepts_80_to_88_seconds(self):
        self.assertIsNone(length_problem(80_000))
        self.assertIsNone(length_problem(88_000))

    def test_too_short_says_to_lower_the_speed(self):
        self.assertIn("lower the speed", length_problem(79_999))

    def test_too_long_says_to_raise_the_speed(self):
        self.assertIn("raise the speed", length_problem(88_001))


if __name__ == "__main__":
    unittest.main()
