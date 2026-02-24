<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\DB;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use PDO;

class PaymentCtrl extends ApiAbstractCtrl
{
    /**
     * Verify an Apple In-App Purchase receipt and credit gold to the player.
     *
     * POST /v1/payment/verifyAppleIap
     * Body: { lang, worldId, uid, receiptData, transactionId, appleProductId, goldProductId }
     */
    public function verifyAppleIap()
    {
        $needs = ['worldId', 'uid', 'receiptData', 'transactionId', 'appleProductId', 'goldProductId'];
        foreach ($needs as $k) {
            if (!isset($this->payload[$k])) {
                throw new MissingParameterException($k);
            }
        }

        $this->response['success'] = false;

        $worldId       = $this->payload['worldId'];
        $uid           = (int) $this->payload['uid'];
        $receiptData   = $this->payload['receiptData'];
        $transactionId = $this->payload['transactionId'];
        $appleProductId = $this->payload['appleProductId'];
        $goldProductId = (int) $this->payload['goldProductId'];

        // 1. Check for duplicate transaction
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT id FROM paymentLog WHERE secureId = :txn AND status = 1 LIMIT 1");
        $stmt->bindValue('txn', $transactionId, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->rowCount()) {
            $this->response['fields']['transactionId'] = 'alreadyProcessed';
            return;
        }

        // 2. Resolve game world
        $server = Server::getServerByWId($worldId);
        if (!$server) {
            $this->response['fields']['worldId'] = 'unknownGameWorld';
            return;
        }

        // 3. Look up gold product
        $stmt = $db->prepare("SELECT * FROM goldProducts WHERE goldProductId = :pid LIMIT 1");
        $stmt->bindValue('pid', $goldProductId, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            $this->response['fields']['goldProductId'] = 'unknownProduct';
            return;
        }

        // 4. Verify receipt with Apple
        $appleResult = $this->verifyWithApple($receiptData);
        if (!$appleResult) {
            $this->response['fields']['receiptData'] = 'appleVerificationFailed';
            return;
        }
        if ($appleResult['status'] !== 0) {
            $this->response['fields']['receiptData'] = 'invalidReceipt';
            $this->response['appleStatus'] = $appleResult['status'];
            return;
        }

        // 5. Find the matching transaction in the receipt
        $matchedTransaction = $this->findTransaction($appleResult, $appleProductId, $transactionId);
        if (!$matchedTransaction) {
            $this->response['fields']['receiptData'] = 'transactionNotFoundInReceipt';
            return;
        }

        // 6. Get player email for logging
        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $stmt = $serverDB->prepare("SELECT name, email FROM users WHERE id = :uid");
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$player) {
            $this->response['fields']['uid'] = 'unknownPlayer';
            return;
        }

        // 7. Log the payment (provider type 10 = Apple IAP)
        $stmt = $db->prepare(
            "INSERT INTO paymentLog (worldUniqueId, uid, email, secureId, paymentProvider, productId, payPrice, status, time, data)
             VALUES (:wid, :uid, :email, :txn, 10, :pid, :price, 0, :time, :data)"
        );
        $stmt->bindValue('wid', $worldId, PDO::PARAM_STR);
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue('email', $player['email'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue('txn', $transactionId, PDO::PARAM_STR);
        $stmt->bindValue('pid', $goldProductId, PDO::PARAM_INT);
        $stmt->bindValue('price', $product['goldProductPrice'], PDO::PARAM_STR);
        $stmt->bindValue('time', time(), PDO::PARAM_INT);
        $stmt->bindValue('data', json_encode([
            'appleProductId' => $appleProductId,
            'appleTransactionId' => $transactionId,
            'appleEnvironment' => $appleResult['environment'] ?? 'unknown',
        ]), PDO::PARAM_STR);
        $stmt->execute();
        $paymentLogId = $db->lastInsertId();

        // 8. Credit gold to player (same logic as completePaymentProcess)
        $realGold = (int) $product['goldProductGold'];

        // Check if offer/promotion is active
        $configStmt = $db->prepare("SELECT `value` FROM paymentConfig WHERE `key` = 'offer' LIMIT 1");
        $configStmt->execute();
        $offerRow = $configStmt->fetch(PDO::FETCH_ASSOC);
        $offerActive = $offerRow && (int) $offerRow['value'] >= time() && $product['goldProductHasOffer'];
        $giftGold = $offerActive ? (int) floor($realGold * 20 / 100) : 0;

        $totalGold = $realGold + $giftGold;

        $stmt = $serverDB->prepare("UPDATE users SET bought_gold = bought_gold + :real, gift_gold = gift_gold + :gift WHERE id = :uid");
        $stmt->bindValue('real', $realGold, PDO::PARAM_INT);
        $stmt->bindValue('gift', $giftGold, PDO::PARAM_INT);
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $result = $stmt->execute();

        if (!$result) {
            // Gold credit failed - mark payment as failed (status 2)
            $updateStmt = $db->prepare("UPDATE paymentLog SET status = 2 WHERE id = :id");
            $updateStmt->bindValue('id', $paymentLogId, PDO::PARAM_INT);
            $updateStmt->execute();

            $this->response['fields']['uid'] = 'goldCreditFailed';
            return;
        }

        // 9. Queue in-game inbox notification
        $stmt = $serverDB->prepare(
            "INSERT INTO buyGoldMessages (uid, gold, type, trackingCode) VALUES (:uid, :gold, 1, :txn)"
        );
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue('gold', $totalGold, PDO::PARAM_INT);
        $stmt->bindValue('txn', $transactionId, PDO::PARAM_STR);
        $stmt->execute();

        // 10. Mark payment as completed (status 1)
        $updateStmt = $db->prepare("UPDATE paymentLog SET status = 1 WHERE id = :id");
        $updateStmt->bindValue('id', $paymentLogId, PDO::PARAM_INT);
        $updateStmt->execute();

        // 11. Log notification
        $notifyText = sprintf(
            "Type: Apple IAP - WID: %s - UID: %s - Player: %s - Gold: %s - Product: %s",
            $worldId, $uid, $player['name'] ?? '?', $totalGold, $appleProductId
        );
        $stmt = $db->prepare("INSERT INTO notifications (message, time) VALUES (:msg, :time)");
        $stmt->bindValue('msg', $notifyText, PDO::PARAM_STR);
        $stmt->bindValue('time', time(), PDO::PARAM_INT);
        $stmt->execute();

        // Success response
        $this->response['success'] = true;
        $this->response['goldAwarded'] = $totalGold;
        $this->response['transactionId'] = $transactionId;
    }

    /**
     * Get gold products available for Apple IAP.
     *
     * POST /v1/payment/getProducts
     * Body: { lang }
     */
    public function getProducts()
    {
        $db = DB::getInstance();
        $stmt = $db->query("SELECT goldProductId, goldProductName, goldProductGold, goldProductPrice, goldProductMoneyUnit, goldProductHasOffer FROM goldProducts WHERE goldProductActive = 1 ORDER BY goldProductGold ASC");

        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'goldProductId' => (int) $row['goldProductId'],
                'name'          => $row['goldProductName'],
                'gold'          => (int) $row['goldProductGold'],
                'price'         => $row['goldProductPrice'],
                'currency'      => $row['goldProductMoneyUnit'],
                'hasOffer'      => (bool) $row['goldProductHasOffer'],
            ];
        }

        $this->response['success'] = true;
        $this->response['products'] = $products;
    }

    /**
     * Verify a receipt with Apple's verifyReceipt endpoint.
     * Tries production first, then sandbox if status 21007.
     */
    private function verifyWithApple(string $receiptData): ?array
    {
        global $globalConfig;
        $sharedSecret = $globalConfig['apple']['sharedSecret'] ?? '';

        $payload = json_encode([
            'receipt-data' => $receiptData,
            'password'     => $sharedSecret,
            'exclude-old-transactions' => true,
        ]);

        // Try production first
        $result = $this->curlApple('https://buy.itunes.apple.com/verifyReceipt', $payload);
        if ($result && $result['status'] === 21007) {
            // Receipt is from sandbox - retry with sandbox URL
            $result = $this->curlApple('https://sandbox.itunes.apple.com/verifyReceipt', $payload);
        }

        return $result;
    }

    private function curlApple(string $url, string $payload): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Find a matching in_app transaction in the Apple receipt.
     */
    private function findTransaction(array $appleResult, string $appleProductId, string $transactionId): ?array
    {
        $inApp = $appleResult['receipt']['in_app'] ?? [];
        foreach ($inApp as $transaction) {
            if (
                $transaction['product_id'] === $appleProductId &&
                $transaction['transaction_id'] === $transactionId
            ) {
                return $transaction;
            }
        }
        return null;
    }
}
