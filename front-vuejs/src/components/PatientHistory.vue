<template>
  <div class="patient-history">
    <div v-if="loading" class="state-box" role="status">
      <b-spinner variant="primary" class="mb-2" />
      <p class="mb-0">Carregando histórico...</p>
    </div>

    <div v-else-if="error" class="state-box" role="alert">
      <p class="state-box__title">Não foi possível carregar o histórico</p>
      <p class="mb-3">{{ error }}</p>
      <b-button variant="primary" @click="load">Tentar novamente</b-button>
    </div>

    <div v-else-if="items.length === 0" class="state-box">
      <p class="state-box__title">Nenhuma alteração registrada</p>
      <p class="mb-0">As alterações feitas neste paciente aparecem aqui.</p>
    </div>

    <template v-else>
      <ol class="history-list" aria-label="Histórico de alterações">
        <li
          v-for="audit in items"
          :key="audit.id"
          class="history-item"
          :class="`history-item--${audit.event}`"
          :data-audit-id="audit.id"
          tabindex="-1"
        >
          <span class="history-item__icon" aria-hidden="true">
            <component :is="eventIcon(audit.event)" />
          </span>
          <div class="history-item__body">
            <p class="history-item__title">
              <strong>{{ eventLabel(audit.event) }}</strong
              ><span class="sr-only">, </span>
              <time class="history-item__time" :datetime="audit.created_at">{{
                formatDateTime(audit.created_at)
              }}</time>
            </p>
            <ul
              v-if="audit.event === 'updated' && changesOf(audit).length"
              class="history-changes"
            >
              <li
                v-for="change in changesOf(audit)"
                :key="change.field"
                class="history-change"
              >
                <span class="history-change__field">{{ change.label }}</span
                ><span class="sr-only">{{ ": " }}</span>
                <span v-if="change.summary" class="history-change__summary">{{
                  change.summary
                }}</span>
                <span v-else class="history-change__values">
                  <span class="sr-only">{{ "de " }}</span>
                  <del class="history-change__old">{{ change.old }}</del>
                  <b-icon-arrow-right
                    class="history-change__arrow"
                    aria-hidden="true"
                  />
                  <span class="sr-only">{{ " para " }}</span>
                  <ins class="history-change__new">{{ change.new }}</ins>
                </span>
              </li>
            </ul>
            <p v-else class="history-item__text">
              {{ eventDescription(audit.event, audit) }}
            </p>
          </div>
        </li>
      </ol>

      <div class="history-footer">
        <p class="history-footer__info" aria-live="polite">
          Mostrando {{ items.length }} de {{ total }}
          {{ total === 1 ? "registro" : "registros" }}
        </p>
        <b-button
          v-if="hasMore"
          variant="outline-primary"
          class="history-footer__more"
          :aria-disabled="loadingMore ? 'true' : null"
          @click="loadMore"
        >
          <b-spinner v-if="loadingMore" small class="mr-1" aria-hidden="true" />
          {{ loadingMore ? "Carregando..." : "Carregar mais" }}
        </b-button>
      </div>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import {
  patientService,
  type Audit,
  type PaginationMeta,
} from "@/services/patientService";
import { getErrorMessage } from "@/services/api";
import { notify } from "@/utils/notify";
import { formatDateTime } from "@/utils/format";
import {
  auditChanges,
  auditEventDescription,
  auditEventIcon,
  auditEventLabel,
  type FormattedChange,
} from "@/utils/audit";

export const HISTORY_PER_PAGE = 10;

/**
 * Change history of a patient (newest first), loaded when mounted — the detail
 * page mounts it the first time the "Histórico" tab opens.
 * The request's IP / user agent are deliberately not shown: without login they
 * identify no one (in Docker the IP is the bridge gateway) and showing them
 * would only expose personal data (LGPD data minimization).
 */
export default defineComponent({
  name: "PatientHistory",
  props: {
    patientId: { type: [String, Number], required: true },
  },
  data() {
    return {
      items: [] as Audit[],
      meta: null as PaginationMeta | null,
      loading: false,
      loadingMore: false,
      error: "",
    };
  },
  computed: {
    total(): number {
      return this.meta?.total ?? this.items.length;
    },
    hasMore(): boolean {
      return !!this.meta && this.meta.current_page < this.meta.last_page;
    },
  },
  watch: {
    patientId() {
      this.load();
    },
  },
  created() {
    this.load();
  },
  methods: {
    formatDateTime,
    eventLabel: auditEventLabel,
    eventIcon: auditEventIcon,
    changesOf(audit: Audit): FormattedChange[] {
      return auditChanges(audit);
    },
    eventDescription: auditEventDescription,
    async load(): Promise<void> {
      this.loading = true;
      this.error = "";
      try {
        const result = await patientService.audits(this.patientId, {
          page: 1,
          per_page: HISTORY_PER_PAGE,
        });
        this.items = result.data;
        this.meta = result.meta;
      } catch (e) {
        this.error = getErrorMessage(e, "Tente novamente em instantes.");
      } finally {
        this.loading = false;
      }
    },
    async loadMore(): Promise<void> {
      if (this.loadingMore || !this.meta) return;
      this.loadingMore = true;
      try {
        const result = await patientService.audits(this.patientId, {
          page: this.meta.current_page + 1,
          per_page: HISTORY_PER_PAGE,
        });
        // New audits may have shifted the pages meanwhile: skip duplicates.
        const seen = new Set(this.items.map((a) => a.id));
        const fresh = result.data.filter((a) => !seen.has(a.id));
        this.items = [...this.items, ...fresh];
        this.meta = result.meta;
        // The button disappears on the last page: move focus to the first new
        // entry instead of letting it fall back to <body>.
        if (!this.hasMore && fresh.length) {
          this.$nextTick(() => {
            const el = this.$el.querySelector<HTMLElement>(
              `[data-audit-id="${fresh[0].id}"]`
            );
            el?.focus();
          });
        }
      } catch (e) {
        notify(
          this.$root,
          "error",
          getErrorMessage(e, "Não foi possível carregar mais registros.")
        );
      } finally {
        this.loadingMore = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.patient-history {
  padding-top: 24px;
}

.history-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.history-item {
  position: relative;
  display: flex;
  gap: 16px;
  padding-bottom: 24px;
  border-radius: $border-radius;

  // Timeline line between icons.
  &:not(:last-child)::before {
    position: absolute;
    top: 40px;
    bottom: 0;
    left: 17px;
    width: 2px;
    background: $border-light;
    content: "";
  }

  &:focus {
    outline: 2px solid $blue;
    outline-offset: 4px;
  }
}

.history-item__icon {
  display: inline-flex;
  flex: 0 0 36px;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  color: $blue;
  font-size: 16px;
  background: rgba($blue, 0.1);
  border-radius: 50%;

  .history-item--created & {
    color: darken($green, 12%);
    background: rgba($green, 0.14);
  }

  .history-item--deleted & {
    color: $red-text;
    background: rgba($red, 0.1);
  }
}

.history-item__body {
  flex: 1 1 auto;
  min-width: 0;
  padding-top: 6px;
}

.history-item__title {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  align-items: baseline;
  margin: 0 0 8px;
  color: $navy;
  font-size: 16px;
}

.history-item__time {
  color: $text-muted;
  font-size: 14px;
}

.history-item__text {
  margin: 0;
  color: $text;
  font-size: 14px;
}

.history-changes {
  margin: 0;
  padding: 0;
  list-style: none;
  border: 1px solid $border-light;
  border-radius: $border-radius;
}

.history-change {
  display: grid;
  grid-template-columns: 180px minmax(0, 1fr);
  gap: 4px 16px;
  padding: 10px 14px;
  font-size: 14px;

  & + & {
    border-top: 1px solid $border-light;
  }
}

.history-change__field {
  color: $navy;
  font-weight: 600;
}

.history-change__values {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 8px;
  align-items: baseline;
  min-width: 0;
}

.history-change__old,
.history-change__new {
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.history-change__old {
  color: $text-muted;
}

.history-change__new {
  color: $text;
  font-weight: 600;
  text-decoration: none;
}

.history-change__arrow {
  flex: 0 0 auto;
  align-self: center;
  color: $text-muted;
}

.history-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
}

.history-footer__info {
  margin: 0;
  color: $text-muted;
  font-size: 14px;
}

@media (max-width: 575.98px) {
  .history-change {
    grid-template-columns: minmax(0, 1fr);
  }
}

.history-change__summary {
  color: $text;
  font-weight: 600;
}
</style>
