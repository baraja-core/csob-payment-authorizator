<?php

declare(strict_types=1);

namespace Baraja\CsobPaymentChecker;


use Baraja\BankTransferAuthorizator\BaseAuthorizator;
use Baraja\PathResolvers\Resolvers\TempDirResolver;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;

final class CsobPaymentAuthorizator extends BaseAuthorizator
{
	private ?Mailbox $mailBox = null;

	private string $tempDir;

	private string $attachmentEncoding = 'windows-1250';


	/**
	 * @param string $imapPath IMAP server and mailbox folder
	 * @param string $login Username for the before configured mailbox
	 * @param string $password Password for the before configured username
	 */
	public function __construct(
		TempDirResolver $tempDirResolver,
		private string $imapPath,
		private string $login,
		private string $password
	) {
		$this->tempDir = $tempDirResolver->get('/csob-payment-checker');
	}


	/**
	 * @return Transaction[]
	 */
	public static function parseTransactions(string $haystack): array
	{
		$haystack = trim(Strings::toAscii(Strings::normalize(Strings::fixEncoding($haystack))));
		if ($haystack === '') {
			throw new \InvalidArgumentException('Input file can not be empty.');
		}
		$relatedDate = null;
		if (preg_match('/Za obdobi od:\s+(\d{2})\.(\d{2})\.(\d{4})/', $haystack, $dateParser) === 1) {
			$relatedDate = new \DateTimeImmutable($dateParser[3] . '-' . $dateParser[2] . '-' . $dateParser[1]);
		}
		if (preg_match('/Mena uctu: ([A-Z]+)/', $haystack, $currencyParser) === 1) {
			$currency = $currencyParser[1];
		} else {
			throw new \InvalidArgumentException('Input does not contain currency info.');
		}
		$payments = explode(str_repeat('=', 99), $haystack);
		$return = [];
		if (isset($payments[1]) === true) {
			$paymentParts = explode(str_repeat('-', 99), $payments[1]);
			foreach ($paymentParts as $payment) {
				$lines = explode("\n", trim($payment));
				if (preg_match('/^\d{2}\.\d{2}\.\s/', $lines[0] ?? '') !== 1) {
					continue;
				}
				$rules = [];
				if (
					isset($lines[0])
					&& preg_match('/^(?<date>\d{2}\.\d{2}\.)\s* (?<name>.{31})(?<accountName>.{21})(?<sekv>.{20})(?<price>.{0,19})/', $lines[0], $basic) === 1
				) {
					$rules[] = $basic;
				}
				if (
					isset($lines[1])
					&& preg_match('/^(?<accountNumber>.{51})(?<variable>.{0,15})(?<ks>[^[\s]{3,})?\s*(?<ss>[^[\s]+)?\s*/', $lines[1], $account) === 1
				) {
					$rules[] = $account;
				}
				if (isset($lines[2]) && ($note = trim($lines[2])) !== '') {
					$rules[] = ['note' => $note];
				}
				$rules = array_merge([], ...$rules);
				$rules = array_map(static fn(string $item): string => trim($item), $rules);
				$rules = array_filter($rules, static fn($key): bool => is_string($key), ARRAY_FILTER_USE_KEY);

				$return[] = new Transaction($relatedDate ?? new \DateTimeImmutable('now'), $currency, $rules);
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
			$mailbox = $this->getMailbox();
			foreach ($mailbox->searchMailbox('ALL') as $mailId) {
				$mail = $mailbox->getMail($mailId);
				if ($mail->hasAttachments()) {
					foreach ($mail->getAttachments() as $attachment) {
						$content = $this->convertAttachmentContent($attachment->getFileInfo(), $attachment->getContents());
						if ($content === null) {
							continue;
						}
						try {
							$return[] = self::parseTransactions($content);
						} catch (\InvalidArgumentException $e) {
							throw new \RuntimeException(sprintf('Attachment "%s": %s', $mailId, $e->getMessage()), $e->getCode(), $e);
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
		if (str_starts_with($fileInfo, 'PDF')) {
			return null;
		}

		// Convert common haystack to UTF-8
		return (string) @iconv($this->attachmentEncoding, 'UTF-8', trim($haystack)) ?: null;
	}
}
