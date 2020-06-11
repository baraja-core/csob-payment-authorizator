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
	private const PATTERN = '(?<date>\d{2}\.\d{2}\.)\s*'
	. '(?<name>.{31})'
	. '(?<accountName>.{21})'
	. '(?<sekv>.{20})'
	. '(?<price>.{19})'
	. '\[breakLine\]'
	. '(?<accountNumber>.{51})'
	. '(?<variable>.{15})'
	. '(?<ks>[^[\s]{3,})\s*'
	. '(?<ss>[^[\s]{1,})?\s*'
	. '(\[breakLine\])?\s*'
	. '(?<note>.+)?\s*';

	/** @var Mailbox|null */
	private $mailBox;

	/** @var string */
	private $imapPath;

	/** @var string */
	private $login;

	/** @var string */
	private $password;

	/** @var string */
	private $tempDir;

	/** @var string */
	private $attachmentEncoding = 'windows-1250';


	/**
	 * @param string $tempDir
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
	 * Download all e-mail messages from IMAP and parse content to entity.
	 *
	 * @return Transaction[]
	 * @throws InvalidParameterException
	 */
	public function getTransactions(): array
	{
		$return = [];
		foreach (($mailbox = $this->getMailbox())->searchMailbox('ALL') as $mailId) {
			if (($mail = $mailbox->getMail($mailId))->hasAttachments()) {
				foreach ($mail->getAttachments() as $attachment) {
					$content = Strings::toAscii(Strings::normalize(Strings::fixEncoding(iconv($this->attachmentEncoding, 'UTF-8', $attachment->getContents()))));
					$relatedDate = null;
					if (preg_match('/Za obdobi od:\s+(\d{2})\.(\d{2})\.(\d{4})/', $content, $dateParser)) {
						$relatedDate = DateTime::from($dateParser[3] . '-' . $dateParser[2] . '-' . $dateParser[1]);
					}
					if (preg_match('/Mena uctu: ([A-Z]+)/', $content, $currencyParser)) {
						$currency = $currencyParser[1];
					} else {
						throw new \RuntimeException('Attachment "' . $mailId . '" does not contain currency info.');
					}
					if (($payments = explode(str_repeat('=', 99), $content)) && isset($payments[1]) === true) {
						foreach (explode(str_repeat('-', 99), $payments[1]) as $payment) {
							if (preg_match('/' . self::PATTERN . '/', str_replace("\n", '[breakLine]', trim($payment)), $parser)) {
								$return[] = new Transaction($relatedDate ?? DateTime::from('now'), $currency, $parser);
							}
						}
					}
				}
			}
		}

		$this->clearTemp();

		return $return;
	}


	/**
	 * @internal
	 * @param string $attachmentEncoding
	 */
	public function setAttachmentEncoding(string $attachmentEncoding): void
	{
		$this->attachmentEncoding = $attachmentEncoding;
	}


	/**
	 * @return Mailbox
	 * @throws InvalidParameterException
	 */
	private function getMailbox(): Mailbox
	{
		if ($this->mailBox === null) {
			FileSystem::createDir($this->tempDir);
			$this->mailBox = new Mailbox($this->imapPath, $this->login, $this->password, $this->tempDir);
		}

		return $this->mailBox;
	}


	private function clearTemp(): void
	{
		FileSystem::delete($this->tempDir);
	}
}