<script setup lang="ts">
// The striped stall awning under the header, with its scalloped edge
// (design-assets.md, "Awning"): 56px stripes on a 64px band over 28px
// scallops, halved on phones. Purely decorative.
</script>

<template>
    <div aria-hidden="true" class="awning">
        <div class="awning-band" />
        <div class="awning-scallops" />
    </div>
</template>

<style scoped>
.awning-band {
    height: 64px;
    border-top: 3px solid var(--outline);
    background: repeating-linear-gradient(
        90deg,
        var(--green) 0 56px,
        var(--stripe) 56px 112px
    );
}

.awning-scallops {
    height: 28px;
    background:
        radial-gradient(circle at 28px 0, var(--green) 27px, transparent 28px) 0
            0 / 112px 28px repeat-x,
        radial-gradient(circle at 84px 0, var(--stripe) 27px, transparent 28px)
            0 0 / 112px 28px repeat-x;
}

@media (max-width: 639.98px) {
    .awning-band {
        height: 30px;
        background: repeating-linear-gradient(
            90deg,
            var(--green) 0 28px,
            var(--stripe) 28px 56px
        );
    }

    .awning-scallops {
        height: 14px;
        background:
            radial-gradient(
                    circle at 14px 0,
                    var(--green) 13.5px,
                    transparent 14px
                )
                0 0 / 56px 14px repeat-x,
            radial-gradient(
                    circle at 42px 0,
                    var(--stripe) 13.5px,
                    transparent 14px
                )
                0 0 / 56px 14px repeat-x;
    }
}
</style>
