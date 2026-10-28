<script setup lang="ts">
import BrandMark from '../components/BrandMark.vue'
</script>
<template>
  <div class="public-page">
    <header class="public-nav">
      <BrandMark />
      <nav>
        <a href="#approach">Our approach</a><RouterLink to="/login">Sign in</RouterLink
        ><RouterLink class="button" to="/register">Create an account ↗</RouterLink>
      </nav>
    </header>
    <main>
      <section class="hero">
        <div>
          <span class="eyebrow">A MORE CONNECTED WAY TO SHIP</span>
          <h1>Great journeys.<br />Smarter <em>beginnings.</em></h1>
          <p>
            One place for your shipping journey. We're building a thoughtful way to compare
            carriers, choose a service and keep everything in view.
          </p>
          <RouterLink class="button" to="/register">Set up your account <span>↗</span></RouterLink>
          <p class="fine">
            Account access is available now. Shipping services are coming in a future phase.
          </p>
        </div>
        <div class="route-art" aria-hidden="true">
          <div class="orbit one"></div>
          <div class="orbit two"></div>
          <span class="route-dot start"></span><span class="route-dot end"></span>
          <div class="parcel"><span>SW</span><small>HANDLE WITH POSSIBILITY</small></div>
          <div class="route-label">
            A good start.<br /><strong>A world of possibilities.</strong>
          </div>
        </div>
      </section>
      <section id="approach" class="approach">
        <span class="eyebrow">BUILT ON THE RIGHT FOUNDATION</span>
        <h2>Clarity at every step.</h2>
        <div class="feature-grid">
          <article>
            <span>01 / YOUR ACCOUNT</span>
            <h3>A secure place to start</h3>
            <p>
              Create and verify your account, manage your profile and stay in control of your
              access.
            </p>
          </article>
          <article>
            <span>02 / COMING NEXT</span>
            <h3>More choice, less friction</h3>
            <p>
              Carrier comparison and shipment booking are planned. No rates or bookings are
              available yet.
            </p>
          </article>
          <article>
            <span>03 / DESIGNED FOR TRUST</span>
            <h3>Thoughtful by design</h3>
            <p>
              Clear permissions, accountable operations and a foundation built for the long journey.
            </p>
          </article>
        </div>
      </section>
    </main>
    <footer><BrandMark /><span>Built for the journey ahead. · Phase 1</span></footer>
  </div>
</template>
