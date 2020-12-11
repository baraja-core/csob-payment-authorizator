<?php

declare(strict_types=1);

namespace Baraja\CsobPaymentChecker;


use Baraja\BankTransferAuthorizator\BaseAuthorizator;
use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;

final class CsobPaymentAuthorizator extends BaseAuthorizator
{
	private ?Mailbox $mailBox = null;

	private string $imapPath;

	private string $login;

	private string $password;

	private string $tempDir;

	private string $attachmentEncoding = 'windows-1250';


	/**
	 * @param string $imapPath IMAP server and mailbox folder
	 * @param string $login Username for the before configured mailbox
	 * @param string $password Password for the before configured username
	 */
	public function __construct(string $tempDir, string $imapPath, string $login, string $password)
	{
		$this->imapPath = $imapPath;
		$this->login = $login;
		$this->password = $password;
		$this->tempDir = $tempDir . '/csob-payment-checker';
	}


	/**
	 * @return Transaction[]
	 */
	public static function parseTransactions(string $haystack): array
	{
		if (($haystack = trim(Strings::toAscii(Strings::normalize(Strings::fixEncoding($haystack))))) === '') {
			throw new \InvalidArgumentException('Input file can not be empty.');
		}
		$relatedDate = null;
		if (preg_match('/Za obdobi od:\s+(\d{2})\.(\d{2})\.(\d{4})/', $haystack, $dateParser)) {
			$relatedDate = DateTime::from($dateParser[3] . '-' . $dateParser[2] . '-' . $dateParser[1]);
		}
		if (preg_match('/Mena uctu: ([A-Z]+)/', $haystack, $currencyParser)) {
			$currency = $currencyParser[1];
		} else {
			throw new \InvalidArgumentException('Input does not contain currency info.');
		}
		$return = [];
		if (($payments = explode(str_repeat('=', 99), $haystack)) && isset($payments[1]) === true) {
			foreach ((array) explode(str_repeat('-', 99), (string) $payments[1]) as $payment) {
				$lines = explode("\n", trim((string) $payment));
				if (!preg_match('/^\d{2}\.\d{2}\.\s/', $lines[0] ?? '')) {
					continue;
				}
				$rules = [];
				if (isset($lines[0]) && preg_match('/^(?<date>\d{2}\.\d{2}\.)\s* (?<name>.{31})(?<accountName>.{21})(?<sekv>.{20})(?<price>.{0,19})/', $lines[0], $basic)) {
					$rules[] = $basic;
				}
				if (isset($lines[1]) && preg_match('/^(?<accountNumber>.{51})(?<variable>.{0,15})(?<ks>[^[\s]{3,})?\s*(?<ss>[^[\s]+)?\s*/', $lines[1], $account)) {
					$rules[] = $account;
				}
				if (isset($lines[2]) && ($note = trim($lines[2])) !== '') {
					$rules[] = ['note' => $note];
				}
				$rules = array_merge([], ...$rules);
				$rules = array_map(fn (string $item) => trim($item), $rules);
				$rules = array_filter($rules, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);

				$return[] = new Transaction($relatedDate ?? DateTime::from('now'), $currency, $rules);
			}
		}

		return $return;
	}


	/**
	 * Download all e-mail messages from IMAP and parse content to entity.
	 *
	 * @return Transaction[]
	 */
	public function getTransactions(): array
	{
		static $cache;
		if ($cache === null) {
			$return = [];
			foreach (($mailbox = $this->getMailbox())->searchMailbox('ALL') as $mailId) {
				if (($mail = $mailbox->getMail($mailId))->hasAttachments()) {
					foreach ($mail->getAttachments() as $attachment) {
						if (($content = $this->convertAttachmentContent($attachment->getFileInfo(), $attachment->getContents())) === null) {
							continue;
						}
						try {
							$return[] = self::parseTransactions($content);
						} catch (\InvalidArgumentException $e) {
							throw new \RuntimeException('Attachment "' . $mailId . '": ' . $e->getMessage(), $e->getCode(), $e);
						}
					}
				}
			}

			$this->clearTemp();
			$cache = array_merge([], ...$return);
		}

		return $cache;
	}


	/**
	 * @internal
	 */
	public function setAttachmentEncoding(string $attachmentEncoding): void
	{
		$this->attachmentEncoding = $attachmentEncoding;
	}


	private function getMailbox(): Mailbox
	{
		if ($this->mailBox === null) {
			FileSystem::createDir($this->tempDir);
			try {
				$this->mailBox = new Mailbox($this->imapPath, $this->login, $this->password, $this->tempDir);
			} catch (InvalidParameterException $e) {
				throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
			}
		}

		return $this->mailBox;
	}


	private function clearTemp(): void
	{
		FileSystem::delete($this->tempDir);
	}


	private function convertAttachmentContent(string $fileInfo, string $haystack): ?string
	{
		if (strncmp($fileInfo, 'PDF', 3) === 0) {
			return null;
		}

		// Convert common haystack to UTF-8
		return ((string) @iconv($this->attachmentEncoding, 'UTF-8', trim($haystack))) ?: null;
	}
}
