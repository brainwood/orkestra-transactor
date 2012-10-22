<?php

namespace Orkestra\Transactor\Transactor\Generic;

use Orkestra\Transactor\AbstractTransactor;
use Orkestra\Transactor\Entity\Account\PointsAccount;
use Orkestra\Transactor\Exception\ValidationException;
use Orkestra\Transactor\Entity\Transaction;
use Orkestra\Transactor\Entity\Result;

/**
 * Handles Points transactions
 */
class PointsTransactor extends AbstractTransactor
{
    /**
     * @var array
     */
    protected static $_supportedNetworks = array(
        Transaction\NetworkType::POINTS
    );

    /**
     * @var array
     */
    protected static $_supportedTypes = array(
        Transaction\TransactionType::SALE,
        Transaction\TransactionType::CREDIT,
        Transaction\TransactionType::REFUND,
    );

    /**
     * Transacts the given transaction
     *
     * @param \Orkestra\Transactor\Entity\Transaction $transaction
     * @param array $options
     *
     * @return \Orkestra\Transactor\Entity\Result
     */
    protected function _doTransact(Transaction $transaction, $options = array())
    {
        $this->_validateTransaction($transaction);

        $account = $transaction->getAccount();
        $result = $transaction->getResult();
        $result->setTransactor($this);

        $adjustment = $transaction->getAmount();
        if (Transaction\TransactionType::SALE === $transaction->getType()->getValue()) {
            if ($transaction->getAmount() > $account->getBalance()) {
                $result->setStatus(new Result\ResultStatus(Result\ResultStatus::DECLINED));
                $result->setMessage('Amount exceeds account balance');

                return $result;
            }

            $adjustment *= -1; // Negate the adjustment
        }

        $result->setStatus(new Result\ResultStatus(Result\ResultStatus::APPROVED));
        $account->adjustBalance($adjustment);

        return $result;
    }

    /**
     * Validates the given transaction
     *
     * @param \Orkestra\Transactor\Entity\Transaction $transaction
     *
     * @throws \Orkestra\Transactor\Exception\ValidationException
     */
    protected function _validateTransaction(Transaction $transaction)
    {
        if (!$transaction->getParent() && in_array($transaction->getType()->getValue(), array(
            Transaction\TransactionType::REFUND
        ))) {
            throw ValidationException::parentTransactionRequired();
        } elseif (!$transaction->getAccount() instanceof PointsAccount) {
            throw ValidationException::invalidAccountType($transaction->getAccount());
        }

        $transaction->setAmount((int)$transaction->getAmount());
    }

    /**
     * Returns the internally used type of this Transactor
     *
     * @return string
     */
    function getType()
    {
        return 'orkestra.generic.points';
    }

    /**
     * Returns the name of this Transactor
     *
     * @return string
     */
    public function getName()
    {
        return 'Points Transactor';
    }
}