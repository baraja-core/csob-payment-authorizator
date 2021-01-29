<?php

declare(strict_types=1);

namespace Baraja\CsobPaymentChecker;


use Nette\Utils\DateTime;

final class Transaction implements \Baraja\BankTransferAuthorizator\Transaction
{
	private \DateTime $date;

	private string $currency;

	private string $name;

	private ?string $accountName;

	private ?string $accountNumber;

	private int $sekv;

	private float $price;

	private ?int $variable;

	private ?int $ks;

	private ?int $ss;

	private ?string $note;


	/**
	 * @param string[] $data
	 */
	public function __construct(\DateTime $relatedDate, string $currency, array $data)
	{
		[$day, $month] = explode('.', trim($data['date']));
		$this->date = DateTime::from($relatedDate->format('Y') . '-' . $month . '-' . $day);
		$this->currency = $currency;
		$this->name = trim($data['name']);
		$this->setAccountName(trim($data['accountName'] ?? '') ?: null);
		$this->accountNumber = trim($data['accountNumber'] ?? '') ?: null;
		$this->sekv = (int) trim($data['sekv']);
		$this->price = (float) str_replace(',', '.', trim($data['price']));
		$this->variable = (int) trim($data['variable'] ?? '') ?: null;
		$this->ks = (int) trim($data['ks'] ?? '') ?: null;
		$this->ss = (int) trim($data['ss'] ?? '') ?: null;
		$this->note = trim($data['note'] ?? '') ?: null;
	}


	public function isVariableSymbol(int $variableSymbol): bool
	{
		return $this->variable === $variableSymbol || $this->isContainVariableSymbolInMessage($variableSymbol);
	}


	public function isContainVariableSymbolInMessage(int $variableSymbol): bool
	{
		return $this->note !== null && strpos($this->note, (string) $variableSymbol) !== false;
	}


	public function getHash(): string
	{
		return md5(
			$this->getVariable() . '_' . $this->getKs() . '_' . $this->getSekv() . '_' . $this->getPrice()
			. '_' . $this->getAccountName() . '_' . $this->getAccountNumber()
			. '_' . $this->getNote() . '_' . $this->getDate()->format('Y-m-d'),
		);
	}


	public function getDate(): \DateTime
	{
		return $this->date;
	}


	public function getCurrency(): string
	{
		return $this->currency;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getAccountName(): ?string
	{
		return $this->accountName;
	}


	public function getAccountNumber(): ?string
	{
		return $this->accountNumber;
	}


	public function getSekv(): int
	{
		return $this->sekv;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function getVariable(): ?int
	{
		return $this->variable;
	}


	public function getKs(): ?int
	{
		return $this->ks;
	}


	public function getSs(): ?int
	{
		return $this->ss;
	}


	public function getNote(): ?string
	{
		return $this->note;
	}


	private function setAccountName(?string $name): void
	{
		if ($name !== null && preg_match('/^[A-Z\s]+$/', $name = trim($name))) {
			$return = '';
			foreach (explode(' ', $name) as $word) {
				$return .= ($return ? ' ' : '') . ucfirst(strtolower($word));
			}
			$this->accountName = $return;

			return;
		}

		$this->accountName = $name;
	}
}
