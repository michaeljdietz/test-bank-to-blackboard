<?php

class TextConverter {
	const QUESTION_REGEX = '/^\s*([0-9]*)\.\s(.*)$/';
	const ANSWER_REGEX = '/^\s*([a-z])\)\s(.*)$/';
	const ANSWER_KEY_REGEX = '/^\s*([0-9]*)\.\s*([0-9a-zA-Z]*)/';

	protected $output_directory = __DIR__.DIRECTORY_SEPARATOR."output";

	protected $question_types = [
		'MC' => 'Multiple-Choice Questions',
		'TF' => 'True or False Questions',
		'ESS' => 'Essay Questions',
	];

	protected $read_fh = null;
	protected $write_fh = null;
	protected $filename = null;
	protected $line;

    /**
     * @throws Exception
     */
    public function convertAll() {
        if (file_exists($this->output_directory)) {
            array_map('unlink', glob($this->output_directory));
        }

        foreach ($this->getFiles() as $filename => $file) {
		    $this->filename = $filename;
			$this->convertFile($filename);	
		}
		
	}

    /**
     * @param $filename
     * @throws Exception
     */
    protected function convertFile($filename) {
		$this->read_fh = fopen($filename, 'r');
		$this->line = fgets($this->read_fh);

		if ($this->line === false) {
			return;
		}

		if (count($question_sections = $this->getQuestionSections())) {
            $answer_key = $this->getAnswerKey();
            $this->writeImportFile($question_sections, $answer_key);
        }
	}

    /**
     * @return array
     * @throws Exception
     */
    protected function getQuestionSections() {
		$question_sections = array();

		do {
			if ($this->isAnswerKey()) {
				break;
			}
			
			if ($this->isQuestionSection()) {
				$question_type = $this->getQuestionType();

				$this->line = fgets($this->read_fh);
				if ($this->line === false) {
					return $question_sections;
				}

				$question_sections[$question_type] = $this->getQuestionSection();
				continue;
			}

            $this->line = fgets($this->read_fh);
		} while ($this->line !== false);

		return $question_sections;
	}

	protected function isQuestionSection() {
		return !is_null($this->getQuestionType());
	}

	protected function isSlideComparisons() {
        return strpos($this->line, "Slide Comparisons") !== false;
    }

	protected function getQuestionType() {
		foreach ($this->question_types as $code => $question_type) {
			if (strpos($this->line, $question_type) !== false) {
				return $code;
			}
		}

		return null;
	}

	protected function isQuestion() {
		$matches = array();
		return preg_match(self::QUESTION_REGEX, $this->line, $matches);
	}

    /**
     * @throws Exception
     */
    protected function getQuestionSection() {
		$questions = array();

		do {
            if ($this->isQuestion()) {
		        $question_number = $this->getQuestionNumber();
                $questions[$question_number]['text'] = $this->getQuestionText();
                $questions[$question_number]['answers'] = $this->getAnswers();
            } elseif (empty(trim($this->line))) {
                $this->line = fgets($this->read_fh);
            } else {
		        break;
            }
        } while ($this->line !== false);

		if (!count($questions)) {
		    throw new Exception('No questions in question section!');
        }

		return $questions;
	}

    /**
     * @return string
     * @throws Exception
     */
    protected function getQuestionNumber() {
        $matches = array();
        if (preg_match(self::QUESTION_REGEX, $this->line, $matches)) {
            return trim($matches[1]);
        }

        throw new Exception('Not a question number!');
    }

    /**
     * @throws Exception
     */
    protected function getQuestionText() {
        $matches = array();
        if (!preg_match(self::QUESTION_REGEX, $this->line, $matches)) {
            throw new Exception('Not a question!');
        }

        $question_text = trim($matches[2]);

        while (($this->line = fgets($this->read_fh)) !== false) {
            if ($this->isAnswer() || $this->isQuestionSection() || $this->isQuestion() || $this->isAnswerKey()) {
                break;
            }

            $question_text .= " " . trim($this->line);
        }

        return $question_text;
    }

	protected function getAnswers() {
		$answers = array();

		do {
			if ($this->isAnswer()) {
				$answers = array_merge($answers, $this->getAnswer());
			} elseif (empty(trim($this->line))) {
                $this->line = fgets($this->read_fh);
            } else {
			    break;
            }
		} while ($this->line !== false);

		return $answers;
	}

	protected function getAnswer() {
		$matches = array();
		preg_match(self::ANSWER_REGEX, $this->line, $matches);

		$answer_letter = trim($matches[1]);
		$answer_text = trim($matches[2]);

        while (($this->line = fgets($this->read_fh)) !== false) {
			if ($this->isAnswerKey() || $this->isQuestion() || $this->isQuestionSection() || $this->isAnswer()) {
				break;
			}

			$answer_text .= " " . trim($this->line);
		}

		return array($answer_letter => $answer_text);
	}

	protected function isAnswer() {
		$matches = array();
		return preg_match(self::ANSWER_REGEX, $this->line, $matches);
	}

	protected function isAnswerKey() {
		if (strpos($this->line, "ANSWER KEY") !== false) {
			return true;
		}

		return false;
	}

    /**
     * @return array
     * @throws Exception
     */
    protected function getAnswerKey() {
        $answer_key_sections = array();

        do {
            if ($this->isQuestionSection()) {
                $question_type = $this->getQuestionType();

                $this->line = fgets($this->read_fh);
                if ($this->line === false) {
                    return $answer_key_sections;
                }

                $answer_key_sections[$question_type] = $this->getAnswerKeySection();
                continue;
            }

            $this->line = fgets($this->read_fh);
        } while ($this->line !== false);

        return $answer_key_sections;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getAnswerKeySection() {
        $questions = array();

        do {
            if ($this->isQuestionSection()) {
                break;
            }

            if ($this->isQuestion()) {
                $question_number = $this->getQuestionNumber();
                $questions[$question_number] = $this->getAnswerKeyAnswer();
            }
        } while (($this->line = fgets($this->read_fh)) !== false);


        if (!count($questions)) {
            throw new Exception("No answers in answer key section!");
        }

        return $questions;

    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function getAnswerKeyAnswer() {
        $matches = array();
        if (preg_match(self::ANSWER_KEY_REGEX, $this->line, $matches)) {
            return $matches[2];
        }

        throw new Exception('Not an answer key line!');
    }

    /**
     * @param $question_sections
     * @param $answer_key
     * @throws Exception
     */
    protected function writeImportFile($question_sections, $answer_key) {
        if (!file_exists($this->output_directory)) {
            mkdir($this->output_directory, 0777, true);
        }

        $this->write_fh = fopen($this->output_directory.DIRECTORY_SEPARATOR.basename($this->filename), 'w');
        $this->writeQuestionSections($question_sections, $answer_key);

        fclose($this->write_fh);
    }

    /**
     * @param $question_sections
     * @param $answer_key
     * @throws Exception
     */
    protected function writeQuestionSections($question_sections, $answer_key) {
        foreach ($question_sections as $question_type => $questions) {
            $current_answer_key = isset($answer_key[$question_type]) ? $answer_key[$question_type] : null;
            $this->writeQuestionSection($question_type, $questions, $current_answer_key);
        }
    }

    /**
     * @param $question_type
     * @param $questions
     * @param $answer_key
     * @throws Exception
     */
    protected function writeQuestionSection($question_type, $questions, $answer_key) {
        foreach ($questions as $question_number => $question) {
            $current_answer_key = isset($answer_key[$question_number]) ? $answer_key[$question_number] : null;
            $this->writeQuestion($question_type, $question, $current_answer_key);
        }
    }

    /**
     * @param $question_type
     * @param $question
     * @param $answer_key
     * @throws Exception
     */
    protected function writeQuestion($question_type, $question, $answer_key) {
        if (!isset($question['text'])) {
            throw new Exception('No question text found!');
        }

        fputs($this->write_fh, $question_type."\t");
        fputs($this->write_fh, $this->getSafeText($question['text']));
        $this->writeAnswers($question, $answer_key);
        fputs($this->write_fh, "\n");
    }

    protected function writeAnswers($question, $answer_key) {
        if (!count($question['answers'])) {
            fputs($this->write_fh, "\t".$this->getSafeText($answer_key));
            return;
        }

        foreach ($question['answers'] as $letter => $answer) {
            fputs($this->write_fh, "\t". $this->getSafeText($answer));
            if (strtoupper($letter) == strtoupper($answer_key)) {
                fputs($this->write_fh, "\tcorrect");
            } else {
                fputs($this->write_fh, "\tincorrect");
            }
        }

        return;
    }

    protected function getSafeText($text) {
        return htmlentities($text);
    }

	protected function getFiles() {
		$files = array();
		$di = new RecursiveDirectoryIterator(__DIR__);
		foreach (new RecursiveIteratorIterator($di) as $filename => $file) {
			if (strpos($filename, '.txt') !== false) {
				$files[$filename] = $file;
			}
		}

		return $files;
	}

	

}

$converter = new TextConverter();
$converter->convertAll();
