<?php
include __DIR__ . '/../includes/header.php';
?>

<style>
.coming-soon-page{
    min-height:calc(100vh - 120px);
    display:flex;
    justify-content:center;
    align-items:center;
    padding:40px;
}

.coming-card{
    width:100%;
    max-width:700px;
    background:#ffffff;
    border-radius:20px;
    padding:60px 40px;
    text-align:center;
    box-shadow:0 15px 40px rgba(0,0,0,.08);
    border-top:6px solid #0d6efd;
}

.coming-icon{
    width:100px;
    height:100px;
    background:#0d6efd;
    color:#fff;
    border-radius:50%;
    margin:auto;
    display:flex;
    justify-content:center;
    align-items:center;
    font-size:45px;
}

.coming-card h1{
    margin-top:25px;
    color:#0d6efd;
    font-weight:700;
}

.coming-card p{
    color:#6c757d;
    font-size:18px;
    margin-top:15px;
}

.loader{
    width:70px;
    height:70px;
    margin:30px auto;
    border:6px solid #dbe8ff;
    border-top:6px solid #0d6efd;
    border-radius:50%;
    animation:spin 1s linear infinite;
}

@keyframes spin{
    to{
        transform:rotate(360deg);
    }
}
</style>

<div class="container-fluid">
    <div class="coming-soon-page">

        <div class="coming-card">

            <div class="coming-icon">
                <i class="fas fa-tools"></i>
            </div>

            <h1>Coming Soon</h1>

            <div class="loader"></div>

            <p>
                This content is currently under development.<br>
                It will be available soon.
            </p>

        </div>

    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>