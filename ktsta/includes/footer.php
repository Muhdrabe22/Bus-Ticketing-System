<footer>
  <div class="footer-grid">
    <div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,var(--orange),var(--orange-dark));display:flex;align-items:center;justify-content:center;font-size:20px;color:white"><i class="fas fa-bus"></i></div>
        <div>
          <div style="font-weight:800;font-size:18px;color:white">KTSTA</div>
          <div style="font-size:11px;color:var(--gray-500)">Katsina State Transport Authority</div>
        </div>
      </div>
      <p style="font-size:13px;line-height:1.7;color:var(--gray-500)">Providing safe, reliable, and affordable public transportation across Katsina State and beyond. Your journey, our commitment.</p>
      <div style="display:flex;gap:10px;margin-top:16px">
        <a href="#" style="width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;color:var(--gray-400);transition:all .2s;font-size:14px" onmouseover="this.style.background='var(--orange)';this.style.color='white'" onmouseout="this.style.background='rgba(255,255,255,.08)';this.style.color='var(--gray-400)'"><i class="fab fa-facebook-f"></i></a>
        <a href="#" style="width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;color:var(--gray-400);transition:all .2s;font-size:14px" onmouseover="this.style.background='var(--orange)';this.style.color='white'" onmouseout="this.style.background='rgba(255,255,255,.08)';this.style.color='var(--gray-400)'"><i class="fab fa-twitter"></i></a>
        <a href="#" style="width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;color:var(--gray-400);transition:all .2s;font-size:14px" onmouseover="this.style.background='var(--orange)';this.style.color='white'" onmouseout="this.style.background='rgba(255,255,255,.08)';this.style.color='var(--gray-400)'"><i class="fab fa-instagram"></i></a>
      </div>
    </div>
    <div>
      <h4 style="color:white;font-size:14px;font-weight:700;margin-bottom:16px">Quick Links</h4>
      <?php $links=[['Home','/index.php'],['Book Ticket','/pages/search.php'],['Routes','/pages/routes.php'],['Schedule','/pages/schedule.php'],['Fare Calculator','/pages/fare-calculator.php'],['Live Tracking','/pages/track.php'],['Charter a Bus','/pages/charter.php'],['Lost & Found','/pages/lost-found.php'],['Reviews','/pages/reviews.php'],['Contact Us','/pages/contact.php']]; ?>
      <?php foreach($links as [$label,$href]): ?>
        <a href="<?= BASE_URL.$href ?>" style="display:block;font-size:13px;color:var(--gray-500);margin-bottom:8px;transition:color .2s" onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--gray-500)'"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4 style="color:white;font-size:14px;font-weight:700;margin-bottom:16px">Services</h4>
      <?php $svcs=['Charter Service','School Transport','Government Contracts','Staff Shuttle','Airport Transfer']; ?>
      <?php foreach($svcs as $s): ?>
        <a href="#" style="display:block;font-size:13px;color:var(--gray-500);margin-bottom:8px;transition:color .2s" onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--gray-500)'"><?= $s ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4 style="color:white;font-size:14px;font-weight:700;margin-bottom:16px">Contact</h4>
      <div style="font-size:13px;color:var(--gray-500);margin-bottom:10px"><i class="fas fa-map-marker-alt" style="color:var(--orange);margin-right:8px"></i>KTSTA HQ, Katsina</div>
      <div style="font-size:13px;color:var(--gray-500);margin-bottom:10px"><i class="fas fa-phone" style="color:var(--orange);margin-right:8px"></i>0800-KTSTA-01</div>
      <div style="font-size:13px;color:var(--gray-500);margin-bottom:10px"><i class="fas fa-envelope" style="color:var(--orange);margin-right:8px"></i>info@ktsta.gov.ng</div>
      <div style="font-size:13px;color:var(--gray-500)"><i class="fas fa-clock" style="color:var(--orange);margin-right:8px"></i>24/7 Support</div>
    </div>
  </div>
  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> KTSTA — Katsina State Government. All rights reserved.</span>
    <span>Built for Katsina State Transport Authority</span>
  </div>
</footer>
</body>
</html>
