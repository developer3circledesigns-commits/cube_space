<footer class="footer">

    <div class="footer-container">

        <div class="footer-col">
            <img src="assets/images/final-logo.png" class="footer-logo" alt="CubeSpace" width="180" height="72">
            <p>Cube Spaces helps businesses discover premium office, managed office and commercial leasing solutions across Chennai and other business locations.</p>
        </div>

        <div class="footer-col">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#about">About Us</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>Solutions</h3>
            <ul>
                <li><a href="managed_offices.php">Managed Office Spaces</a></li>
                <li><a href="furnished_offices.php">Furnished / Unfurnished Office Spaces</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>Contact Info</h3>
            <ul class="contact-list">
                <li><i class="fa-solid fa-phone"></i><a href="tel:+919962200015">+91 99622 00015</a></li>
                <li><i class="fa-solid fa-envelope"></i><a href="mailto:sales@falconlease.com">sales@falconlease.com</a></li>
                <li><i class="fa-solid fa-location-dot"></i><span>Chennai</span></li>
                <li><i class="fa-solid fa-envelope"></i><a href="mailto:hafiz@falconlease.com">hafiz@falconlease.com</a></li>
                <li><i class="fa-solid fa-globe"></i><a href="https://www.cubespaces.in" target="_blank" rel="noopener noreferrer">www.cubespaces.in</a></li>
            </ul>
        </div>

    </div>

    <!-- <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> CubeSpace. All Rights Reserved.</p>
        <p>Design and Developed By Circle Designs</p>
    </div> -->

</footer>

<script>
(function(){
    var base = (document.querySelector('meta[name="app-base"]') || {}).content || '';
    var url = base + '/api/track_visit.php';
    var page = (window.location.pathname || '').split('/').pop() || 'index';
    var name = page.replace(/\.php$/i,'');
    var activity = name.charAt(0).toUpperCase() + name.slice(1);
    activity = activity.replace(/[-_]/g,' ');
    var fd = new FormData();
    fd.append('url', window.location.href);
    fd.append('activity', activity);
    if (navigator.sendBeacon) {
        navigator.sendBeacon(url, fd);
    } else {
        var x = new XMLHttpRequest();
        x.open('POST', url, true);
        x.send(fd);
    }
})();
</script>
