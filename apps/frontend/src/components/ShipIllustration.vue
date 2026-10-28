<template>
  <svg viewBox="0 0 520 340" fill="none" aria-hidden="true" class="ship-illustration">
    <defs>
      <linearGradient id="sea" x1="0" y1="220" x2="520" y2="340" gradientUnits="userSpaceOnUse">
        <stop stop-color="#b3eadb" />
        <stop offset="1" stop-color="#e4f5ff" />
      </linearGradient>
      <linearGradient id="hull" x1="105" y1="190" x2="400" y2="260">
        <stop stop-color="#146a62" />
        <stop offset="1" stop-color="#073f4b" />
      </linearGradient>
    </defs>
    <circle cx="290" cy="148" r="130" fill="#e1f5ef" />
    <circle cx="414" cy="66" r="35" fill="#f1f9fd" />
    <path
      d="M0 248C75 194 133 300 217 241C302 185 345 274 422 245C468 228 498 230 520 245V340H0Z"
      fill="url(#sea)"
    />
    <path d="M94 204H409L376 254H140Z" fill="url(#hull)" />
    <path d="M283 113H344V205H283Z" fill="#d7f0e5" />
    <path d="M295 96H317V115H295Z" fill="#419a87" />
    <path d="M305 95V60" stroke="#166459" stroke-width="4" />
    <path d="M317 130H333V147H317Z" fill="#79c6b0" />
    <path d="M123 158H182V204H123Z" fill="#52b89c" />
    <path d="M184 158H243V204H184Z" fill="#2e987e" />
    <path d="M245 158H282V204H245Z" fill="#73c8ac" />
    <path d="M157 112H214V156H157Z" fill="#8dd8bc" />
    <path d="M217 112H274V156H217Z" fill="#48a98c" />
    <path d="M190 66H244V110H190Z" fill="#b5e7d1" />
    <path d="M105 175H365" stroke="#fff" stroke-opacity=".4" stroke-width="3" />
    <path
      d="M139 204V158M198 204V158M172 112V156M234 66V110M252 158V204"
      stroke="#fff"
      stroke-opacity=".35"
      stroke-width="2"
    />
    <path
      d="M0 282C82 240 111 320 202 284C298 246 338 314 430 280C468 265 498 272 520 288"
      stroke="#fff"
      stroke-opacity=".7"
      stroke-width="4"
    />
    <path
      d="M49 104C61 97 72 97 85 104M373 67C385 60 396 60 409 67"
      stroke="#9acabb"
      stroke-width="3"
      stroke-linecap="round"
    />
  </svg>
</template>
