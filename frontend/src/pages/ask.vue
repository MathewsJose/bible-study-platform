<template>
  <div class="mx-auto grid max-w-[1280px] gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="panel-surface px-5 py-5 sm:px-6">
      <div class="flex flex-col gap-3 border-b border-[var(--stroke)] pb-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-2xl">
          <p class="eyebrow">Private Alpha</p>
          <h1 class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-50">
            Ask a grounded Catholic study question
          </h1>
          <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
            Answers are generated from retrieved Bible, Catechism, and Church Father sources. Verify important conclusions against the cited sources.
          </p>
        </div>

        <NuxtLink
          to="/"
          class="soft-ring inline-flex items-center justify-center rounded-xl border border-[var(--stroke)] bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900"
        >
          Reading Room
        </NuxtLink>
      </div>

      <form class="mt-5 space-y-4" @submit.prevent="alpha.ask">
        <label class="block">
          <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Alpha Token</span>
          <input
            v-model="alpha.token"
            type="password"
            autocomplete="off"
            class="soft-ring h-11 w-full rounded-xl border border-[var(--stroke)] bg-white px-3 text-sm text-slate-900 dark:bg-slate-950 dark:text-slate-100"
            placeholder="Paste your private access token"
          >
        </label>

        <label class="block">
          <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Question</span>
          <textarea
            v-model="alpha.question"
            rows="4"
            class="soft-ring w-full resize-y rounded-xl border border-[var(--stroke)] bg-white px-4 py-3 text-base leading-7 text-slate-900 dark:bg-slate-950 dark:text-slate-100"
            maxlength="1000"
          />
        </label>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="submit"
            class="soft-ring inline-flex h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950"
            :disabled="alpha.loading"
          >
            {{ alpha.loading ? 'Asking...' : 'Ask' }}
          </button>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            Request ID: {{ alpha.answer?.requestId || 'created after answer' }}
          </p>
        </div>
      </form>

      <AppError v-if="alpha.error" class="mt-5" :message="alpha.error" />

      <section v-if="alpha.answer" class="mt-6 space-y-5">
        <div class="rounded-2xl border border-[var(--stroke)] bg-white p-5 dark:bg-slate-950">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Answer</h2>
            <span class="rounded-full bg-[var(--accent-soft)] px-3 py-1 text-xs font-semibold text-[var(--accent)]">
              {{ alpha.answer.provider || 'provider unknown' }} / {{ alpha.answer.model || 'model unknown' }}
            </span>
          </div>
          <p class="mt-4 whitespace-pre-wrap text-base leading-8 text-slate-700 dark:text-slate-200">
            {{ alpha.answer.answer || 'Evidence was not found for this question.' }}
          </p>
        </div>

        <div class="rounded-2xl border border-[var(--stroke)] bg-white p-5 dark:bg-slate-950">
          <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Sources</h2>
          <div v-if="alpha.hasSources" class="mt-4 grid gap-3">
            <button
              v-for="source in alpha.answer.sources"
              :key="source.id || source.reference"
              type="button"
              class="soft-ring rounded-2xl border border-[var(--stroke)] bg-slate-50 px-4 py-4 text-left transition hover:-translate-y-0.5 hover:bg-white dark:bg-slate-900 dark:hover:bg-slate-950"
              @click="alpha.openReference(source.reference)"
            >
              <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--accent)]">
                {{ sourceTypeLabel(source.source_type) }}
              </span>
              <span class="mt-2 block text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ source.reference }}
              </span>
              <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">
                {{ source.title || source.source_name }}
              </span>
            </button>
          </div>
          <p v-else class="mt-4 rounded-2xl border border-dashed border-[var(--stroke)] px-4 py-5 text-sm text-slate-500 dark:text-slate-400">
            No supporting sources were returned.
          </p>
        </div>

        <div class="rounded-2xl border border-[var(--stroke)] bg-white p-5 dark:bg-slate-950">
          <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Was this answer helpful?</h2>
          <div class="mt-4 flex flex-wrap gap-3">
            <button
              type="button"
              class="soft-ring rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="alpha.feedbackLoading"
              @click="alpha.sendFeedback('helpful')"
            >
              Yes
            </button>
            <button
              type="button"
              class="soft-ring rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950"
              :disabled="alpha.feedbackLoading"
              @click="alpha.sendFeedback('not_helpful')"
            >
              No
            </button>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)]">
            <select
              v-model="alpha.selectedReason"
              class="soft-ring h-11 rounded-xl border border-[var(--stroke)] bg-white px-3 text-sm text-slate-900 dark:bg-slate-950 dark:text-slate-100"
            >
              <option value="">Reason</option>
              <option value="incorrect_answer">Incorrect answer</option>
              <option value="incorrect_citation">Incorrect citation</option>
              <option value="missing_information">Missing information</option>
              <option value="other">Other</option>
            </select>
            <input
              v-model="alpha.comment"
              class="soft-ring h-11 rounded-xl border border-[var(--stroke)] bg-white px-3 text-sm text-slate-900 dark:bg-slate-950 dark:text-slate-100"
              maxlength="1000"
              placeholder="Optional comment"
            >
          </div>
          <p v-if="alpha.feedbackMessage" class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">
            {{ alpha.feedbackMessage }}
          </p>
        </div>
      </section>
    </section>

    <aside class="panel-surface h-fit px-5 py-5">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Opened Source</h2>
      <p v-if="alpha.resolvingReference" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
        Opening {{ alpha.resolvingReference }}...
      </p>
      <div v-else-if="alpha.resolvedSource?.error" class="mt-4">
        <AppError :message="alpha.resolvedSource.error" />
      </div>
      <div v-else-if="resolvedDocument" class="mt-4 space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--accent)]">
          {{ sourceTypeLabel(resolvedDocument.source_type) }}
        </p>
        <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ resolvedDocument.reference }}</h3>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ resolvedDocument.title || resolvedDocument.source_name }}</p>
        <p class="text-sm leading-7 text-slate-700 dark:text-slate-200">{{ resolvedDocument.content }}</p>
      </div>
      <p v-else class="mt-4 text-sm leading-7 text-slate-500 dark:text-slate-400">
        Click a returned citation to inspect the supporting source.
      </p>
    </aside>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import AppError from '../components/common/AppError.vue';
import { useAlphaAskStore } from '../stores/alphaAskStore';
import { sourceTypeLabel } from '../services/knowledgeAlphaContract';

const alpha = useAlphaAskStore();

const resolvedDocument = computed(() => {
  if (!alpha.resolvedSource) {
    return null;
  }

  const payload = alpha.resolvedSource?.data || alpha.resolvedSource || {};

  if (Array.isArray(payload.results)) {
    return payload.results[0] || null;
  }

  const document = payload.document || payload;

  return document.reference || document.content || document.title || document.source_name
    ? document
    : null;
});
</script>
