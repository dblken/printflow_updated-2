<?php
// Logged-in users always leave the public home (back button / bookmark still hits server → redirect)
require_once __DIR__ . '/../includes/auth.php';
redirect_logged_in_from_landing_page();

$page_title = 'PrintFlow - Your Trusted Printing Shop';
$use_landing_css = true;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Fetch featured products for the homepage showcase
$featured_products = db_query(
    "SELECT product_id, name, category, price, description, product_image, stock_quantity 
     FROM products 
     WHERE is_featured = 1 AND status = 'Activated'
     ORDER BY name ASC LIMIT 6"
) ?: [];
?>

<section class="lp-hero">
    <?php $nav_header_class = 'lp-hero-nav sticky top-0 z-50'; require __DIR__ . '/../includes/nav-header.php'; ?>
    <div class="lp-wrap">
        <div class="lp-hero-inner">
            <div class="lp-hero-content">
                <p class="lp-hero-tag">Professional Printing Solutions</p>
                <h1 class="lp-hero-title">Print Your Ideas<br><span>with Precision</span></h1>
                <p class="lp-hero-desc">Transform your creative vision into reality. High-quality printing for tarpaulins, apparel, stickers, and custom designs—crafted with care and ready for pickup.</p>
                <div class="lp-hero-btns">
                    <?php if (!is_logged_in()): ?>
                        <a href="#" data-auth-modal="register" class="lp-btn lp-btn-primary">Get Started Free</a>
                        <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-outline">Browse Products</a>
                    <?php else: ?>
                        <?php $dash_link = ($user_type === 'Customer') ? 'services.php' : 'dashboard.php'; ?>
                        <?php $dash_text = ($user_type === 'Customer') ? 'Go to Services' : 'Go to Dashboard'; ?>
                        <a href="/printflow/<?php echo strtolower($user_type); ?>/<?php echo $dash_link; ?>" class="lp-btn lp-btn-primary"><?php echo $dash_text; ?></a>
                    <?php endif; ?>
                </div>
                <div class="lp-stats">
                    <div><p class="lp-stat-num">500+</p><p class="lp-stat-label">Happy Clients</p></div>
                    <div><p class="lp-stat-num">10K+</p><p class="lp-stat-label">Orders Completed</p></div>
                    <div><p class="lp-stat-num">Trusted</p><p class="lp-stat-label">Customer Support</p></div>
                </div>
            </div>
            <div class="lp-hero-visual">
                <style>
                @keyframes lp-floatA { 0%,100%{transform:rotate(-6deg) translateY(0)} 50%{transform:rotate(-6deg) translateY(-10px)} }
                @keyframes lp-floatB { 0%,100%{transform:rotate(4deg) translateY(0)} 50%{transform:rotate(4deg) translateY(-14px)} }
                @keyframes lp-floatC { 0%,100%{transform:rotate(-2deg) translateY(0)} 50%{transform:rotate(-2deg) translateY(-8px)} }
                @keyframes lp-fadeUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }
                .lp-showcase {
                    position: relative;
                    width: 100%;
                    max-width: 460px;
                    height: 420px;
                    margin: 0 auto;
                }
                .lp-showcase-glow {
                    position: absolute;
                    inset: -40px;
                    background: radial-gradient(ellipse 75% 65% at 55% 50%, rgba(83,197,224,.28) 0%, rgba(50,161,196,.1) 45%, transparent 70%);
                    pointer-events: none;
                    filter: blur(18px);
                }
                .lp-sc-card {
                    position: absolute;
                    border-radius: 1.1rem;
                    overflow: hidden;
                    box-shadow:
                        0 20px 50px rgba(0,0,0,.65),
                        0 0 0 1px rgba(83,197,224,.12),
                        inset 0 1px 0 rgba(255,255,255,.08);
                    transition: transform .4s cubic-bezier(.34,1.56,.64,1), box-shadow .4s ease;
                    cursor: pointer;
                    opacity: 0;
                    backdrop-filter: blur(2px);
                }
                /* teal color blend overlay on every card */
                .lp-sc-card::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(135deg, rgba(0,35,43,.45) 0%, rgba(50,161,196,.18) 100%);
                    mix-blend-mode: multiply;
                    z-index: 1;
                    pointer-events: none;
                    border-radius: inherit;
                }
                /* glass shimmer top edge */
                .lp-sc-card::after {
                    content: '';
                    position: absolute;
                    top: 0; left: 0; right: 0;
                    height: 1px;
                    background: linear-gradient(90deg, transparent, rgba(83,197,224,.4), transparent);
                    z-index: 2;
                    pointer-events: none;
                }
                .lp-sc-card:hover {
                    box-shadow:
                        0 30px 65px rgba(0,0,0,.75),
                        0 0 35px rgba(83,197,224,.25),
                        0 0 0 1px rgba(83,197,224,.3),
                        inset 0 1px 0 rgba(255,255,255,.12);
                    z-index: 20 !important;
                }
                .lp-sc-card img {
                    width: 100%; height: 100%;
                    object-fit: cover;
                    display: block;
                    filter: brightness(.78) saturate(.7) hue-rotate(165deg) sepia(.15);
                    transition: filter .4s ease, transform .4s ease;
                }
                .lp-sc-card:hover img {
                    filter: brightness(.88) saturate(.85) hue-rotate(165deg) sepia(.1);
                    transform: scale(1.04);
                }
                .lp-sc-label {
                    position: absolute;
                    bottom: 0; left: 0; right: 0;
                    padding: .6rem .85rem;
                    background: linear-gradient(to top, rgba(0,21,27,.92) 0%, rgba(0,35,43,.6) 60%, transparent 100%);
                    font-size: .7rem;
                    font-weight: 700;
                    color: #7fd3ea;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                    z-index: 3;
                }
                .lp-sc-a {
                    width: 200px; height: 240px;
                    top: 60px; left: 0;
                    z-index: 1;
                    animation: lp-fadeUp .7s .1s forwards, lp-floatA 6s 1s ease-in-out infinite;
                }
                .lp-sc-a:hover { transform: rotate(-6deg) scale(1.06) !important; }
                .lp-sc-b {
                    width: 210px; height: 255px;
                    top: 20px; left: 120px;
                    z-index: 3;
                    animation: lp-fadeUp .7s .25s forwards, lp-floatB 7s 1s ease-in-out infinite;
                }
                .lp-sc-b:hover { transform: rotate(4deg) scale(1.06) !important; }
                .lp-sc-c {
                    width: 185px; height: 185px;
                    top: 200px; left: 230px;
                    z-index: 2;
                    animation: lp-fadeUp .7s .4s forwards, lp-floatC 5.5s 1s ease-in-out infinite;
                }
                .lp-sc-c:hover { transform: rotate(-2deg) scale(1.06) !important; }
                </style>

                <div class="lp-showcase">
                    <div class="lp-showcase-glow"></div>

                    <div class="lp-sc-card lp-sc-a">
                        <img src="/printflow/public/images/products/signage1.jpg" alt="Tarpaulin & Signage">
                        <div class="lp-sc-label">Tarpaulin & Signage</div>
                    </div>

                    <div class="lp-sc-card lp-sc-b">
                        <img src="/printflow/public/images/tshirt_replacement/Front Center Print.webp" alt="T-shirt Printing">
                        <div class="lp-sc-label">T-shirt Printing</div>
                    </div>

                    <div class="lp-sc-card lp-sc-c">
                        <img src="/printflow/public/images/products/Glass Stickers  Wall  Frosted Stickers.png" alt="Stickers & Decals">
                        <div class="lp-sc-label">Stickers & Decals</div>
                    </div>
                </div>
            </div>
        </div>
        <a href="#lp-services" class="lp-scroll-hint" id="lp-scroll-hint" aria-label="Scroll to content">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
        </a>
    </div>
</section>

<section class="lp-section-light" id="lp-services">
    <div class="lp-wrap">
        <div class="lp-heading-wrap">
            <p class="lp-heading-label">What We Offer</p>
            <h2 class="lp-heading">Our Premium Services</h2>
            <p class="lp-heading-desc">Quality printing solutions tailored to your needs. From custom designs to bulk orders.</p>
        </div>
        <div class="lp-cards">
            <div class="lp-card">
                <div class="lp-card-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg></div>
                <h3 class="lp-card-title">Apparel & Merch</h3>
                <p class="lp-card-text">Custom t-shirts, hoodies, and uniforms with premium fabric and long-lasting prints.</p>
                <a href="<?php echo $url_products; ?>?category=Apparel" class="lp-card-link">Learn more →</a>
            </div>
            <div class="lp-card">
                <div class="lp-card-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>
                <h3 class="lp-card-title">Business Signage</h3>
                <p class="lp-card-text">Tarpaulins, standees, and banners. Weatherproof materials for indoor and outdoor use.</p>
                <a href="<?php echo $url_products; ?>?category=Signage" class="lp-card-link">Learn more →</a>
            </div>
            <div class="lp-card">
                <div class="lp-card-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></div>
                <h3 class="lp-card-title">Stickers & Decals</h3>
                <p class="lp-card-text">Custom stickers, labels, and decals. Waterproof, durable, any size or shape.</p>
                <a href="<?php echo $url_products; ?>?category=Stickers" class="lp-card-link">Learn more →</a>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($featured_products)): ?>
<section class="lp-section" id="lp-featured">
    <div class="lp-wrap">
        <div class="lp-heading-wrap">
            <p class="lp-heading-label">Handpicked For You</p>
            <h2 class="lp-heading">Featured Products</h2>
            <p class="lp-heading-desc">Our most popular printing products — ready to order with fast turnaround.</p>
        </div>

        <!-- Infinite auto-scroll carousel -->
        <style>
        @keyframes lp-marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .lp-carousel-clip {
            overflow: hidden;
            margin-top: 2rem;
        }
        .lp-carousel-outer {
            position: relative;
            padding: 2rem 0;
        }
        .lp-carousel-track {
            display: flex;
            width: max-content;
            animation: lp-marquee 38s linear infinite;
            will-change: transform;
        }
        .lp-carousel-clip:hover .lp-carousel-track {
            animation-play-state: paused;
        }
        .lp-carousel-item {
            width: 280px;
            flex-shrink: 0;
            margin-right: 1.5rem;
            background: var(--lp-surface);
            border: 1px solid var(--lp-border);
            border-radius: 1rem;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .4s cubic-bezier(.34,1.56,.64,1), box-shadow .4s, border-color .4s;
            position: relative;
            cursor: pointer;
        }
        .lp-carousel-item:hover {
            transform: scale(1.05) translateY(-8px);
            box-shadow: 0 20px 45px rgba(0,0,0,.5), 0 0 25px rgba(83,197,224,.18);
            border-color: rgba(83,197,224,.45);
            z-index: 10;
        }
        </style>

        <div class="lp-carousel-clip"><div class="lp-carousel-outer">
            <div class="lp-carousel-track">
                <?php foreach (array_merge($featured_products, $featured_products) as $fp): ?>
                <div class="lp-carousel-item">
                    <div class="lp-prod-img">
                        <?php if (!empty($fp['product_image'])): ?>
                            <img src="/printflow/public/assets/uploads/products/<?php echo htmlspecialchars($fp['product_image']); ?>"
                                 alt="<?php echo htmlspecialchars($fp['name']); ?>"
                                 style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <span class="lp-prod-placeholder">📦</span>
                        <?php endif; ?>
                        <span style="position:absolute; top:10px; right:10px; background:#fbbf24; color:#1a1a1a; font-size:10px; font-weight:800; padding:3px 9px; border-radius:20px; letter-spacing:.05em;">⭐ Featured</span>
                        <?php if ($fp['stock_quantity'] <= 0): ?>
                            <span style="position:absolute; top:10px; left:10px; background:rgba(239,68,68,.9); color:white; font-size:10px; font-weight:700; padding:3px 9px; border-radius:20px;">Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    <div class="lp-prod-content">
                        <div class="lp-prod-meta">
                            <span class="lp-prod-cat"><?php echo htmlspecialchars($fp['category']); ?></span>
                            <?php if ($fp['stock_quantity'] > 0): ?>
                                <span class="lp-prod-stock-ok">● In Stock</span>
                            <?php else: ?>
                                <span class="lp-prod-stock-out">● Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="lp-prod-title"><?php echo htmlspecialchars($fp['name']); ?></h3>
                        <p class="lp-prod-desc"><?php echo htmlspecialchars(mb_substr($fp['description'] ?? '', 0, 70)); ?><?php echo mb_strlen($fp['description'] ?? '') > 70 ? '…' : ''; ?></p>
                        <div class="lp-prod-footer">
                            <span class="lp-prod-price">₱<?php echo number_format($fp['price'], 2); ?></span>
                            <?php if ($fp['stock_quantity'] > 0): ?>
                                <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-primary" style="padding:.5rem 1.1rem; font-size:.8rem; box-shadow:none;">Order Now</a>
                            <?php else: ?>
                                <span style="color:var(--lp-muted); font-size:.8rem;">Unavailable</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div></div>

        <div style="text-align:center; margin-top:2.5rem;">
            <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-outline">View All Products →</a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="lp-section-light">
    <div class="lp-wrap">
        <div class="lp-two-col">
            <div class="lp-order-2">
                <div class="lp-feature-box">
                    <img src="/printflow/public/assets/uploads/shop_logo_1774059623.jpg" alt="PrintFlow store" class="lp-feature-box-image">
                </div>
            </div>
            <div class="lp-order-1">
                <p class="lp-heading-label">Why Choose Us</p>
                <h2 class="lp-heading">Why Choose PrintFlow?</h2>
                <p class="lp-heading-desc">We combine cutting-edge printing technology with expertise to produce exceptional results every time.</p>
                <ul class="lp-list">
                    <li>
                        <div class="lp-list-icon indigo"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                        <div><h4 class="lp-list-title">Premium Quality Materials</h4><p class="lp-list-desc">Finest materials for vibrant colors and long-lasting durability.</p></div>
                    </li>
                    <li>
                        <div class="lp-list-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                        <div><h4 class="lp-list-title">Fast Turnaround</h4><p class="lp-list-desc">Most orders ready within 24–48 hours.</p></div>
                    </li>
                    <li>
                        <div class="lp-list-icon amber"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        <div><h4 class="lp-list-title">Affordable Pricing</h4><p class="lp-list-desc">Competitive rates, no hidden fees, bulk discounts.</p></div>
                    </li>
                </ul>
                <div class="lp-mt"><a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-primary">Get Started Today</a></div>
            </div>
        </div>
    </div>
</section>

<section class="lp-section-cta">
    <div class="lp-wrap">
        <div class="lp-cta-inner">
            <h2 class="lp-cta-title">Ready to Bring Your Ideas to Life?</h2>
            <p class="lp-cta-desc">Join hundreds of satisfied customers who trust PrintFlow for their printing needs.</p>
            <div class="lp-cta-btns">
                <?php if (!is_logged_in()): ?>
                    <a href="#" data-auth-modal="register" class="lp-btn lp-btn-primary">Create Free Account</a>
                    <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-outline">View Products</a>
                <?php else: ?>
                    <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-primary">Browse Products</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
