<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

$captcha = [];
$resdata = [];

// Step 1: Get CAPTCHA
$capCurl = curl_init();
curl_setopt_array($capCurl, [
    CURLOPT_URL => 'https://kycapizone.in/api/v2/captcha/generation.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CUSTOMREQUEST => 'GET',
]);
$capRes = curl_exec($capCurl);
curl_close($capCurl);

$captcha = json_decode($capRes, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['uid']) && !empty($_POST['captcha_code']) && !empty($_POST['captcha_txn'])) {
    $uid = trim($_POST['uid']);
    $captcha_code = trim($_POST['captcha_code']);
    $captcha_txn = trim($_POST['captcha_txn']);
    $appliedby = $udata['phone'];

    // Optional fee check
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='AgriFarmer_KYC_Fee'"));
    $fee = $price['price'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        $api_zone = "API_KEY_PASTE"; // Buy APi From This Website https://apizone.co.in ( Design & Development By KPS )
        $url = "https://kycapizone.in/api/v2/Aadhar_Advance/verifyUid.php?api_key=$api_zone&uid=$uid&captcha=$captcha_code&captcha_txn=$captcha_txn";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $apiResult = json_decode($response, true);

        if ($apiResult && $apiResult['success'] === true && $apiResult['response_code'] === '100') {
            $resdata = $apiResult['result'];
            // Wallet deduction
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$appliedby', '$fee', '$debit_fee', 'Aadhaar UID Verification', '1', 'Debit')");
        } else {
            echo "<script>Swal.fire('Failed', 'API Error: " . htmlspecialchars($apiResult['message'] ?? 'Unknown error') . "', 'error');</script>";
        }
    } else {
        echo "<script>Swal.fire('Low Balance', 'Please recharge your wallet!', 'warning');</script>";
    }
}
?>
<!--start page wrapper -->
		<div class="page-wrapper">
			<div class="page-content">
    <div class="row">
      <!-- Left Panel -->
      <div class="col-md-4">
        <form method="POST" class="card p-3 shadow">
          <h5 class="mb-3 text-danger">Aadhaar UID Verification</h5>
          <div class="form-group mb-2">
            <label>Aadhaar Number</label>
            <input type="text" name="uid" maxlength="12" minlength="12" required class="form-control" placeholder="Enter UID">
          </div>

          <div class="form-group mb-2">
            <label>Enter CAPTCHA</label>
            <input type="text" name="captcha_code" required class="form-control" placeholder="Enter shown CAPTCHA">
          </div>

          <div class="form-group mb-2">
            <label>CAPTCHA Image</label><br>
            <?php if (!empty($captcha['image'])): ?>
              <img src="<?= $captcha['image'] ?>" style="border:1px solid #aaa;">
              <input type="hidden" name="captcha_txn" value="<?= $captcha['txnId'] ?>">
            <?php else: ?>
              <p class="text-danger">Failed to load CAPTCHA</p>
            <?php endif; ?>
          </div>

          <div class="form-group mb-3">
            <label>Fee</label>
            <input type="text" class="form-control" value="₹ <?= $fee ?>" readonly>
          </div>

          <button type="submit" class="btn btn-success w-100"><i class="fa fa-check"></i> Verify</button>
        </form>
      </div>

      <!-- Right Panel -->
      <?php if (!empty($resdata)): ?>
      <div class="col-md-8">
        <div class="card p-3 shadow">
          <h5>✅ Aadhaar Verification Result</h5>
          <table class="table table-bordered table-striped">
            <tr><th>Status</th><td><?= htmlspecialchars($resdata['status']) ?></td></tr>
            <tr><th>Status Message</th><td><?= htmlspecialchars($resdata['statusMessage']) ?></td></tr>
            <tr><th>Gender</th><td><?= htmlspecialchars($resdata['gender']) ?></td></tr>
            <tr><th>Age Band</th><td><?= htmlspecialchars($resdata['ageBand']) ?></td></tr>
            <tr><th>Address</th><td><?= htmlspecialchars($resdata['address']) ?></td></tr>
            <tr><th>Masked Mobile</th><td><?= htmlspecialchars($resdata['maskedMobileNumber']) ?></td></tr>
            <tr><th>Response Code</th><td><?= htmlspecialchars($resdata['aadhaarStatusCode']) ?></td></tr>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include('footer.php'); ?>
