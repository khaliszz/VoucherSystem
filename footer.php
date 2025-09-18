<?php
// footer.php
?>
<footer class="site-footer">
    <div class="footer-container">
        <!-- Logo + About Section -->
        <div class="footer-section about">
            <img src="images/logo.png" alt="Optima Bank Logo" class="footer-logo">
            <p>
                Redeem your points, unlock rewards, and enjoy exclusive vouchers. 
                At OptimaBank, we value your loyalty and aim to give back with every transaction.
            </p>
        </div>

        <!-- Support Section -->
        <div class="footer-section support">
            <h3>Support</h3>
            <ul>
                <li><a href="">FAQ</a></li>
                <li><a href="">Terms & Conditions</a></li>
                <li><a href="">Privacy Policy</a></li>
            </ul>
        </div>

        <!-- Contact / Social Media -->
        <div class="footer-section contact">
            <h3>Contact Us</h3>
            <p>Email: <a href="mailto:hello@optimabank.gr">hello@optimabank.gr</a></p>
            <p>Phone:+30 210 8173000</p>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> OptimaBank Loyalty. All rights reserved.</p>
    </div>
</footer>

<!-- Footer Styles -->
<style>
.site-footer {
    background: #2E003E; /* Optima purple */
    color: #ddd;
    padding: 40px 20px 20px;
    font-family: Arial, sans-serif;
    margin-top: 40px;
}
.footer-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: auto;
    align-items: flex-start;
}
.footer-logo {
    width: 160px;
    margin-bottom: 15px;
}
.footer-section h3 {
    color: #fff;
    margin-bottom: 15px;
    font-size: 18px;
}
.footer-section p,
.footer-section ul,
.footer-section li,
.footer-section a {
    font-size: 14px;
    line-height: 1.6;
    color: #bbb;
    text-decoration: none;
}
.footer-section a:hover {
    color: #ff9800; /* Orange accent */
}
.footer-section ul {
    list-style: none;
    padding: 0;
}
.footer-section ul li {
    margin-bottom: 10px;
}
.social-icons {
    margin-top: 10px;
}
.social-icons a {
    display: inline-block;
    margin-right: 10px;
}
.social-icons img {
    width: 20px;
    height: 20px;
    filter: invert(80%);
}
.footer-bottom {
    border-top: 1px solid #4b006e;
    margin-top: 30px;
    text-align: center;
    padding-top: 15px;
    font-size: 13px;
    color: #aaa;
}
</style>
