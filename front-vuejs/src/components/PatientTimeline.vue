<template>
  <div class="patient-timeline">
    <div v-if="loading" class="state-box" role="status">
      <b-spinner variant="primary" class="mb-2" />
      <p class="mb-0">Carregando linha do tempo...</p>
    </div>

    <div v-else-if="error" class="state-box" role="alert">
      <p class="state-box__title">Não foi possível carregar a linha do tempo</p>
      <p class="mb-3">{{ error }}</p>
      <b-button variant="primary" @click="load">Tentar novamente</b-button>
    </div>

    <div v-else-if="items.length === 0" class="state-box">
      <p class="state-box__title">Nada registrado ainda</p>
      <p class="mb-0">
        Atendimentos e alterações do cadastro aparecem aqui, do mais recente
        para o mais antigo.
      </p>
    </div>

    <template v-else>
      <ol class="timeline" aria-label="Linha do tempo do paciente">
        <li v-for="day in days" :key="day.date" class="timeline-day">
          <h2 :id="`timeline-day-${day.date}`" class="timeline-day__title">
            {{ day.label }}
          </h2>
          <ol class="timeline-items">
            <li
              v-for="item in day.items"
              :key="keyOf(item)"
              class="timeline-item"
              :class="`timeline-item--${item.type}`"
              :data-timeline-key="keyOf(item)"
              tabindex="-1"
            >
              <template v-if="item.type === 'consultation'">
                <span
                  class="timeline-item__icon timeline-item__icon--consultation"
                  aria-hidden="true"
                >
                  <b-icon-calendar-check />
                </span>
                <div class="timeline-item__body">
                  <p class="timeline-item__title">
                    <router-link
                      :to="{
                        name: 'consultations.show',
                        params: { id: String(item.consultation.id) },
                      }"
                      class="timeline-item__link"
                      >Atendimento</router-link
                    ><span class="sr-only">, </span>
                    <time
                      class="timeline-item__time"
                      :datetime="item.consultation.scheduled_at"
                      >{{ rangeOf(item.consultation) }}</time
                    ><span class="sr-only">, </span>
                    <StatusBadge
                      :status="item.consultation.status"
                      sr-prefix="Status:"
                    />
                  </p>
                  <p class="timeline-item__text">
                    {{ item.consultation.professional_name }}
                    <template v-if="item.consultation.professional_crm">
                      · CRM {{ item.consultation.professional_crm }}
                    </template>
                  </p>
                  <p
                    v-if="refractionOf(item.consultation)"
                    class="timeline-item__refraction"
                  >
                    <b-icon-clipboard-check aria-hidden="true" class="mr-1" />
                    <span class="timeline-item__refraction-label"
                      >Refração:</span
                    >
                    {{ refractionOf(item.consultation) }}
                  </p>
                </div>
              </template>

              <template v-else>
                <span
                  class="timeline-item__icon"
                  :class="`timeline-item__icon--${item.audit.event}`"
                  aria-hidden="true"
                >
                  <component :is="eventIcon(item.audit.event)" />
                </span>
                <div class="timeline-item__body">
                  <p class="timeline-item__title">
                    <strong>{{ eventLabel(item.audit.event) }}</strong
                    ><span class="sr-only">, </span>
                    <time
                      class="timeline-item__time"
                      :datetime="item.audit.created_at"
                      >{{ timeOf(item.audit.created_at) }}</time
                    >
                  </p>
                  <ul
                    v-if="
                      item.audit.event === 'updated' &&
                      changesOf(item.audit).length
                    "
                    class="timeline-changes"
                  >
                    <li
                      v-for="change in changesOf(item.audit)"
                      :key="change.field"
                      class="timeline-change"
                    >
                      <span class="timeline-change__field">{{
                        change.label
                      }}</span
                      ><span class="sr-only">{{ ": " }}</span>
                      <span
                        v-if="change.summary"
                        class="timeline-change__summary"
                        >{{ change.summary }}</span
                      >
                      <span v-else class="timeline-change__values">
                        <span class="sr-only">{{ "de " }}</span>
                        <del>{{ change.old }}</del>
                        <b-icon-arrow-right
                          class="timeline-change__arrow"
                          aria-hidden="true"
                        />
                        <span class="sr-only">{{ " para " }}</span>
                        <ins>{{ change.new }}</ins>
                      </span>
                    </li>
                  </ul>
                  <p v-else class="timeline-item__text">
                    {{ eventDescription(item.audit.event, item.audit) }}
                  </p>
                </div>
              </template>
            </li>
          </ol>
        </li>
      </ol>

      <div class="list-footer">
        <p class="list-footer__info" aria-live="polite">
          Mostrando {{ items.length }} de {{ total }}
          {{ total === 1 ? "registro" : "registros" }}
        </p>
        <b-button
          v-if="hasMore"
          variant="outline-primary"
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
import StatusBadge from "@/components/StatusBadge.vue";
import {
  consultationService,
  type Consultation,
  type TimelineItem,
} from "@/services/consultationService";
import type { Audit, PaginationMeta } from "@/services/patientService";
import { getErrorMessage } from "@/services/api";
import { notify } from "@/utils/notify";
import {
  auditChanges,
  auditEventDescription,
  auditEventIcon,
  auditEventLabel,
  type FormattedChange,
} from "@/utils/audit";
import { timePart, timeRange } from "@/utils/datetime";
import { refractionSummary } from "@/utils/eyeExam";
import {
  groupTimelineByDay,
  mergeTimeline,
  timelineKey,
  type TimelineDay,
} from "@/utils/timeline";

export const TIMELINE_PER_PAGE = 15;

/**
 * "Linha do tempo" tab: consultations and change-history entries merged by the
 * API (newest first), grouped by São Paulo day, paged with "Carregar mais".
 */
export default defineComponent({
  name: "PatientTimeline",
  components: { StatusBadge },
  props: {
    patientId: { type: [String, Number], required: true },
  },
  data() {
    return {
      items: [] as TimelineItem[],
      meta: null as PaginationMeta | null,
      loading: false,
      loadingMore: false,
      error: "",
    };
  },
  computed: {
    days(): TimelineDay[] {
      return groupTimelineByDay(this.items);
    },
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
    keyOf: timelineKey,
    eventLabel: auditEventLabel,
    eventIcon: auditEventIcon,
    eventDescription: auditEventDescription,
    changesOf(audit: Audit): FormattedChange[] {
      return auditChanges(audit);
    },
    rangeOf(c: Consultation): string {
      return timeRange(c.scheduled_at, c.duration_minutes);
    },
    timeOf(value: string): string {
      return timePart(value);
    },
    refractionOf(c: Consultation): string {
      return refractionSummary(c.eye_exam);
    },
    async load(): Promise<void> {
      this.loading = true;
      this.error = "";
      try {
        const result = await consultationService.timeline(this.patientId, {
          page: 1,
          per_page: TIMELINE_PER_PAGE,
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
        const result = await consultationService.timeline(this.patientId, {
          page: this.meta.current_page + 1,
          per_page: TIMELINE_PER_PAGE,
        });
        const before = this.items.length;
        this.items = mergeTimeline(this.items, result.data);
        this.meta = result.meta;
        const firstNew = this.items[before];
        if (!this.hasMore && firstNew) {
          this.$nextTick(() => {
            this.$el
              .querySelector<HTMLElement>(
                `[data-timeline-key="${timelineKey(firstNew)}"]`
              )
              ?.focus();
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

.patient-timeline {
  padding-top: 24px;
}

.timeline,
.timeline-items,
.timeline-changes {
  margin: 0;
  padding: 0;
  list-style: none;
}

.timeline-day + .timeline-day {
  margin-top: 8px;
}

.timeline-day__title {
  position: sticky;
  top: 0;
  z-index: 1;
  margin: 0 0 12px;
  padding: 6px 0;
  color: $navy;
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  background: #fff;
  border-bottom: 1px solid $border-light;
}

.timeline-item {
  position: relative;
  display: flex;
  gap: 16px;
  padding-bottom: 20px;
  border-radius: $border-radius;

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

.timeline-item__icon {
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

  &--consultation {
    color: #fff;
    background: $navy;
  }

  &--created {
    color: darken($green, 12%);
    background: rgba($green, 0.14);
  }

  &--deleted {
    color: $red-text;
    background: rgba($red, 0.1);
  }
}

.timeline-item__body {
  flex: 1 1 auto;
  min-width: 0;
  padding-top: 6px;
}

.timeline-item__title {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  align-items: center;
  margin: 0 0 4px;
  color: $navy;
  font-size: 16px;
}

.timeline-item__link {
  font-weight: 800;
}

.timeline-item__time {
  color: $text-muted;
  font-size: 14px;
  font-variant-numeric: tabular-nums;
}

.timeline-item__text {
  margin: 0;
  color: $text;
  font-size: 14px;
}

.timeline-item__refraction {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
  margin: 8px 0 0;
  padding: 4px 10px;
  color: $navy;
  font-size: 14px;
  font-variant-numeric: tabular-nums;
  background: $page-bg;
  border-radius: $border-radius;
}

.timeline-item__refraction-label {
  font-weight: 700;
}

.timeline-changes {
  border: 1px solid $border-light;
  border-radius: $border-radius;
}

.timeline-change {
  display: grid;
  grid-template-columns: 180px minmax(0, 1fr);
  gap: 4px 16px;
  padding: 8px 12px;
  font-size: 14px;

  & + & {
    border-top: 1px solid $border-light;
  }

  del,
  ins {
    overflow-wrap: anywhere;
    white-space: pre-line;
  }

  del {
    color: $text-muted;
  }

  ins {
    color: $text;
    font-weight: 600;
    text-decoration: none;
  }
}

.timeline-change__field {
  color: $navy;
  font-weight: 600;
}

.timeline-change__values {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 8px;
  align-items: baseline;
  min-width: 0;
}

.timeline-change__arrow {
  flex: 0 0 auto;
  align-self: center;
  color: $text-muted;
}

.list-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
}

.list-footer__info {
  margin: 0;
  color: $text-muted;
  font-size: 14px;
}

@media (max-width: 575.98px) {
  .timeline-change {
    grid-template-columns: minmax(0, 1fr);
  }
}

.timeline-change__summary {
  color: $text;
  font-weight: 600;
}
</style>
