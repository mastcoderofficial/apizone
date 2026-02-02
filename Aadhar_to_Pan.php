<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// ---------------- PRICE FETCH ----------------
$price = mysqli_fetch_assoc(mysqli_query(
    $ahk_conn,
    "SELECT price FROM pricing WHERE service_name='Aadhaar_To_PAN_fee'"
));
$fee = $price['price'] ?? 10;

$resdata = null;

// ---------------- FORM SUBMIT ----------------
if (isset($_POST['aadhaar_no'])) {

    $aadhaar_no = trim($_POST['aadhaar_no']);
    $userid     = $udata['phone'];
    $oldBalance = $udata['balance'];

    // Aadhaar basic validation
    if (!preg_match('/^[0-9]{12}$/', $aadhaar_no)) {
        echo "<script>
            Swal.fire('Invalid Aadhaar','Please enter 12 digit Aadhaar number','error');
        </script>";
        goto page;
    }

    if ($oldBalance < $fee) {
        echo "<script>
            Swal.fire({
                icon:'error',
                title:'Low Balance',
                text:'Please recharge your wallet',
                timer:2000
            });
            setTimeout(()=>{window.location='wallet.php'},1500);
        </script>";
        goto page;
    }

    // ---------------- API CALL ----------------
    $api_zone = "API_KEY_PASTE"; // Buy APi From This Website https://apizone.co.in ( Design & Development By APIZONE )
    $url = "https://kycapizone.in/api/panno/instant/aadhaar_to_pan.php?api_key=$api_zone&aadhaar_no=$aadhaar_no";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $resdata = json_decode($response, true);

    // ---------------- RESPONSE HANDLE ----------------
    if (empty($resdata)) {
        echo "<script>
            Swal.fire('Error','API not responding','error');
        </script>";
        goto page;
    }

    // ❌ INVALID AADHAAR
    if ($resdata['success'] === false) {
        echo "<script>
            Swal.fire('Error','{$resdata['message']}','error');
        </script>";
        goto page;
    }

    // ⚠️ UID NOT LINKED (422)
    if ($resdata['status_code'] == 422) {

        if (!empty($resdata['billable']) && $resdata['billable'] === true) {
            $newBal = $oldBalance - $fee;

            mysqli_query($ahk_conn,
                "UPDATE users SET balance='$newBal' WHERE phone='$userid'"
            );

            mysqli_query($ahk_conn,
                "INSERT INTO wallethistory 
                (userid, amount, balance, purpose, status, type)
                VALUES
                ('$userid','$fee','$newBal','Aadhaar → PAN Check','1','Debit')"
            );
        }

        echo "<script>
            Swal.fire('Not Linked','{$resdata['message']}','warning');
        </script>";
    }

    // ✅ SUCCESS (100)
    if ($resdata['status_code'] == 100) {

        if (!empty($resdata['billable']) && $resdata['billable'] === true) {
            $newBal = $oldBalance - $fee;

            mysqli_query($ahk_conn,
                "UPDATE users SET balance='$newBal' WHERE phone='$userid'"
            );

            mysqli_query($ahk_conn,
                "INSERT INTO wallethistory 
                (userid, amount, balance, purpose, status, type)
                VALUES
                ('$userid','$fee','$newBal','Aadhaar → PAN Check','1','Debit')"
            );
        }
    }
}
page:
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="content-wrap">
 <div class="main">
<div class="page-wrapper">
    <div class="page-content">
        <div class="main-container">
<!-- ---------------- FORM ---------------- -->
<div class="col-lg-4">
<div class="card">
<div class="card-body">

<div class="alert alert-info">Aadhaar → PAN Verification</div>

<form method="POST">
    <label>Enter Aadhaar Number</label>
    <input type="text" name="aadhaar_no" class="form-control" maxlength="12" required>

    <div class="mt-3 d-flex justify-content-between">
        <input class="form-control w-50" value="Fee ₹ <?= $fee ?>" readonly>
        <button class="btn btn-primary">Submit</button>
    </div>
</form>

</div>
</div>
</div>

<!-- ---------------- RESULT ---------------- -->
<?php if (!empty($resdata) && $resdata['status_code'] == 100) { ?>
<div class="col-lg-8">
<div class="card" style="background:#F4ECF7">
<div class="card-body">

<h5>Aadhaar → PAN Result</h5>

<table class="table table-bordered">
<tr>
    <th>Aadhaar</th>
    <td><?= htmlspecialchars($resdata['data']['aadhaar_number']) ?></td>
</tr>
<tr>
    <th>PAN</th>
    <td><?= htmlspecialchars($resdata['data']['pan_number']) ?></td>
</tr>
<tr>
    <th>Application No</th>
    <td><?= htmlspecialchars($resdata['application_no']) ?></td>
</tr>
<tr>
    <th>Request Time</th>
    <td><?= htmlspecialchars($resdata['timestamps']['request_time']) ?></td>
</tr>
</table>

<div class="alert alert-success">
    <?= htmlspecialchars($resdata['message']) ?>
</div>

</div>
</div>
</div>
<?php } ?>

</div>
</div>

<?php include('footer.php'); ?>
