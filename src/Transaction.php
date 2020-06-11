<?php

declare(strict_types=1);

namespace Baraja\CsobPaymentChecker;


use Nette\SmartObject;
use Nette\Utils\DateTime;

final class Transaction implements \Baraja\BankTransferAuthorizator\Transaction
{
	use SmartObject;

	/** @var \DateTime */
	private $date;

	/** @var string */
	private $currency;

	/** @var string */
	private $name;

	/** @var string|null */
	private $accountName;

	/** @var string|null */
	private $accountNumber;

	/** @var int */
	private $sekv;

	/** @var float */
	private $price;

	/** @var int|null */
	private $variable;

	/** @var string|null */
	private $ks;

	/** @var string|null */
	private $ss;

	/** @var string|null */
	private $note;


	/**
	 * @param \DateTime $relatedDate
	 * @param string $currency
	 * @param string[] $data
	 */
	public function __construct(\DateTime $relatedDate, string $currency, array $data)
	{
		[$day, $month] = explode('.', trim($data['date']));
		$this->date = DateTime::from($relatedDate->format('Y') . '-' . $month . '-' . $day);
		$this->currency = $currency;
		$this->name = trim($data['name']);
		$this->accountName = trim($data['accountName']) ?: null;
		$this->accountNumber = trim($data['accountNumber']) ?: null;
		$this->sekv = (int) trim($data['sekv']);
		$this->price = (float) str_replace(',', '.', trim($data['price']));
		$this->variable = (int) trim($data['variable']) ?: null;
		$this->ks = trim($data['ks'] ?? '') ?: null;
		$this->ss = trim($data['ss'] ?? '') ?: null;
		$this->note = preg_replace('/(\[breakLine\]\s+)/', '', trim($data['note'] ?? '')) ?: null;
	}


	/**
	 * @param int $variableSymbol
	 * @return bool
	 */
	public function isVariableSymbol(int $variableSymbol): bool
	{
		return $this->variable === $variableSymbol || $this->isContainVariableSymbolInMessage($variableSymbol);
	}


	/**
	 * @param int $variableSymbol
	 * @return bool
	 */
	public function isContainVariableSymbolInMessage(int $variableSymbol): bool
	{
		return $this->note !== null && strpos($this->note, (string) $variableSymbol) !== false;
	}


	/**
	 * @return \DateTime
	 */
	public function getDate(): \DateTime
	{
		return $this->date;
	}


	/**
	 * @return string
	 */
	public function getCurrency(): string
	{
		return $this->currency;
	}


	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}


	/**
	 * @return string|null
	 */
	public function getAccountName(): ?string
	{
		return $this->accountName;
	}


	/**
	 * @return string|null
	 */
	public function getAccountNumber(): ?string
	{
		return $this->accountNumber;
	}


	/**
	 * @return int
	 */
	public function getSekv(): int
	{
		return $this->sekv;
	}


	/**
	 * @return float
	 */
	public function getPrice(): float
	{
		return $this->price;
	}


	/**
	 * @return int|null
	 */
	public function getVariable(): ?int
	{
		return $this->variable;
	}


	/**
	 * @return string|null
	 */
	public function getKs(): ?string
	{
		return $this->ks;
	}


	/**
	 * @return string|null
	 */
	public function getSs(): ?string
	{
		return $this->ss;
	}


	/**
	 * @return string|null
	 */
	public function getNote(): ?string
	{
		return $this->note;
	}
}