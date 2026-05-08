import { useEffect, useState } from "react";
import { useApiQuery, useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Card, CardContent, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { ChannelToggle } from "./ChannelToggle.jsx";
import { NotificationsSkeleton } from "./NotificationsSkeleton.jsx";

/**
 * Local form state mirrors the server, but only saves on click. We deliberately
 * don't auto-save on toggle — a deliberate Save button is the standard mental
 * model for preferences pages and prevents misclicks.
 */
export default function NotificationsTab() {
	const query = useApiQuery("/notifications/preferences");

	const [vouchers, setVouchers] = useState(true);
	const [points, setPoints] = useState(true);
	const [savedAt, setSavedAt] = useState(null);
	const [error, setError] = useState(null);

	// Hydrate the form from the server on first load + after a save.
	useEffect(() => {
		if (!query.data) return;
		setVouchers(query.data.subscribe_vouchers);
		setPoints(query.data.subscribe_points);
		setSavedAt(query.data.updated_at);
	}, [query.data]);

	const mutation = useApiMutation("put", "/notifications/preferences", {
		invalidate: ["/notifications/preferences"],
		onSuccess: (data) => {
			setSavedAt(data.updated_at);
			setError(null);
		},
		onError: (err) => setError(err.message ?? "Could not save preferences."),
	});

	if (query.isLoading) return <NotificationsSkeleton />;
	if (query.isError)
		return <p className="zc-text-rose-600">{query.error?.message ?? "Failed to load preferences."}</p>;

	const dirty = query.data
		? vouchers !== query.data.subscribe_vouchers || points !== query.data.subscribe_points
		: false;

	const submit = (e) => {
		e.preventDefault();
		setError(null);
		mutation.mutate({ subscribe_vouchers: vouchers, subscribe_points: points });
	};

	return (
		<div className="zc-max-w-2xl zc-space-y-5">
			<Card>
				<CardHeader>
					<CardTitle>Notification preferences</CardTitle>
					<p className="zc-text-sm zc-text-zinc-500">
						Choose what you want to hear from us. You can change this any time.
					</p>
				</CardHeader>

				<CardContent>
					<form onSubmit={submit} className="zc-space-y-1">
						<ChannelToggle
							id="zc-notif-vouchers"
							title="New vouchers and promotions"
							description="Be the first to know when we publish a new voucher you can claim."
							checked={vouchers}
							onChange={setVouchers}
						/>
						<ChannelToggle
							id="zc-notif-points"
							title="Points and rewards updates"
							description="Reminders about your points balance and milestone rewards."
							checked={points}
							onChange={setPoints}
						/>

						<div className="zc-flex zc-items-center zc-justify-between zc-gap-3 zc-pt-4">
							<SavedHint savedAt={savedAt} dirty={dirty} error={error} />
							<Button type="submit" disabled={!dirty || mutation.isPending} loading={mutation.isPending}>
								Save preferences
							</Button>
						</div>
					</form>
				</CardContent>
			</Card>

			<UnsubscribeAllNotice
				vouchers={vouchers}
				points={points}
			/>
		</div>
	);
}

function SavedHint({ savedAt, dirty, error }) {
	if (error)
		return <p className="zc-text-sm zc-text-rose-700">{error}</p>;
	if (dirty)
		return <p className="zc-text-sm zc-text-amber-700">Unsaved changes</p>;
	if (savedAt)
		return (
			<p className="zc-text-sm zc-text-zinc-500">
				Last saved {formatRelative(savedAt)}
			</p>
		);
	return null;
}

function UnsubscribeAllNotice({ vouchers, points }) {
	if (vouchers || points) return null;
	return (
		<div className="zc-rounded-lg zc-border zc-border-amber-200 zc-bg-amber-50 zc-p-4 zc-text-sm zc-text-amber-900">
			<p className="zc-font-medium">You've turned off all CRM notifications.</p>
			<p className="zc-mt-1">
				You'll still receive transactional emails about orders. Toggle a channel back
				on if you want to hear about rewards.
			</p>
		</div>
	);
}

function formatRelative(iso) {
	if (!iso) return "—";
	const diff = Date.now() - new Date(iso).getTime();
	const min  = 60 * 1000;
	const hour = 60 * min;
	const day  = 24 * hour;
	if (diff < min)     return "just now";
	if (diff < hour)    return `${Math.floor(diff / min)} min ago`;
	if (diff < day)     return `${Math.floor(diff / hour)}h ago`;
	if (diff < 7 * day) return `${Math.floor(diff / day)}d ago`;
	return new Date(iso).toLocaleDateString();
}
