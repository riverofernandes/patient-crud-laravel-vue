<template>
  <div class="status-actions-wrap">
    <div
      v-if="actions.length"
      class="status-actions"
      :class="{ 'status-actions--small': small }"
      role="group"
      :aria-label="groupLabel"
    >
      <b-button
        v-for="action in actions"
        :key="action.target"
        :variant="action.variant"
        :size="small ? 'sm' : undefined"
        class="status-actions__btn"
        :aria-label="buttonLabel(action)"
        :aria-disabled="busy ? 'true' : null"
        :data-action="action.target"
        @click="request(action)"
      >
        <b-spinner
          v-if="busy && pending && pending.target === action.target"
          small
          class="mr-1"
          aria-hidden="true"
        />
        <component :is="action.icon" v-else class="mr-1" aria-hidden="true" />
        {{ action.label }}
      </b-button>
    </div>

    <b-modal
      :visible="!!confirming"
      :title="confirming ? confirming.confirm.title : ''"
      title-tag="h2"
      header-tag="div"
      footer-tag="div"
      centered
      no-close-on-backdrop
      :no-close-on-esc="busy"
      :hide-header-close="busy"
      dialog-class="confirm-delete-modal"
      header-close-label="Fechar"
      :return-focus="returnFocus"
      @hide="onHide"
      @hidden="onHidden"
    >
      <template #modal-header-close>
        <b-icon-x aria-hidden="true" />
      </template>
      <p class="mb-0">{{ confirming ? confirming.confirm.message : "" }}</p>
      <template #modal-footer>
        <button
          type="button"
          class="btn-link-danger"
          :disabled="busy"
          @click="confirming = null"
        >
          Voltar
        </button>
        <b-button
          variant="danger"
          class="status-actions__confirm"
          :disabled="busy"
          @click="confirmPending"
        >
          <b-spinner v-if="busy" small class="mr-1" aria-hidden="true" />
          {{ confirming ? confirming.confirm.confirmLabel : "" }}
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import type { BvModalEvent } from "bootstrap-vue";
import {
  consultationService,
  type Consultation,
} from "@/services/consultationService";
import {
  getErrorMessage,
  getValidationErrors,
  isNotFound,
} from "@/services/api";
import { allowedActions, type StatusAction } from "@/utils/consultationStatus";
import { notify } from "@/utils/notify";

/**
 * Status buttons allowed by the transition table for a consultation. Cancel
 * and no-show ask for confirmation first. Performs the PATCH itself and emits
 * `changed` with the updated consultation. The clicked button usually
 * disappears afterwards, so focus moves to `focusTarget`.
 */
export default defineComponent({
  name: "ConsultationStatusActions",
  props: {
    consultation: {
      type: Object as PropType<Consultation>,
      required: true,
    },
    small: { type: Boolean, default: false },
    /** Context for the accessible names, e.g. "de Ana Souza às 14:30". */
    context: { type: String, default: "" },
    /** Selector focused after a status change. */
    focusTarget: { type: String, default: "#main-content h1" },
  },
  data() {
    return {
      busy: false,
      pending: null as StatusAction | null,
      confirming: null as (StatusAction & { confirm: object }) | null,
      returnFocus: null as string | null,
    };
  },
  computed: {
    actions(): StatusAction[] {
      return allowedActions(this.consultation.status);
    },
    groupLabel(): string {
      return this.context
        ? `Ações do atendimento ${this.context}`
        : "Ações do atendimento";
    },
  },
  methods: {
    buttonLabel(action: StatusAction): string | undefined {
      return this.context
        ? `${action.label}: atendimento ${this.context}`
        : undefined;
    },
    request(action: StatusAction): void {
      if (this.busy) return;
      if (action.confirm) {
        this.returnFocus = null;
        this.confirming = action as StatusAction & { confirm: object };
        return;
      }
      void this.apply(action);
    },
    async confirmPending(): Promise<void> {
      if (!this.confirming) return;
      const ok = await this.apply(this.confirming);
      if (ok) {
        // The opener disappears with the status change.
        this.returnFocus = this.focusTarget;
        this.confirming = null;
      }
    },
    onHide(event: BvModalEvent): void {
      if (this.busy) event.preventDefault();
    },
    onHidden(): void {
      // After a confirmed change the opener is gone: focus the target (made
      // focusable here; BootstrapVue skips non-focusable return targets).
      if (this.returnFocus) this.focusAfterChange();
      this.confirming = null;
      this.returnFocus = null;
    },
    focusAfterChange(): void {
      this.$nextTick(() => {
        const el = document.querySelector<HTMLElement>(this.focusTarget);
        if (!el) return;
        if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "-1");
        el.focus({ preventScroll: true });
      });
    },
    async apply(action: StatusAction): Promise<boolean> {
      this.busy = true;
      this.pending = action;
      try {
        const updated = await consultationService.changeStatus(
          this.consultation.id,
          action.target
        );
        notify(this.$root, "success", action.success);
        this.$emit("changed", updated, action);
        if (!action.confirm) this.focusAfterChange();
        return true;
      } catch (error) {
        const errors = getValidationErrors(error);
        let message = getErrorMessage(
          error,
          "Não foi possível alterar o status do atendimento."
        );
        if (errors?.status?.length) message = errors.status[0];
        else if (isNotFound(error)) message = "Atendimento não encontrado.";
        notify(this.$root, "error", message);
        if (errors || isNotFound(error)) {
          // Stale status (changed elsewhere): let the parent reload.
          this.$emit("stale");
          this.confirming = null;
        }
        return false;
      } finally {
        this.busy = false;
        this.pending = null;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
.status-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.status-actions__btn {
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
}

.status-actions--small .status-actions__btn {
  font-size: 14px;
  line-height: 20px;
}
</style>
