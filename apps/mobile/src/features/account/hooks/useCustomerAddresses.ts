import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createCustomerAddress,
  deleteCustomerAddress,
  fetchCustomerAddresses,
  setDefaultCustomerAddress,
  updateCustomerAddress,
  type AddressInput,
} from '../api/addressesApi';

export function addressesQueryKey() {
  return ['account', 'addresses'] as const;
}

export function useCustomerAddresses() {
  return useQuery({
    queryKey: addressesQueryKey(),
    queryFn: fetchCustomerAddresses,
  });
}

export function useAddressMutations() {
  const queryClient = useQueryClient();

  async function invalidate() {
    await queryClient.invalidateQueries({ queryKey: addressesQueryKey() });
  }

  const create = useMutation({
    mutationFn: (input: AddressInput) => createCustomerAddress(input),
    onSuccess: invalidate,
  });
  const update = useMutation({
    mutationFn: (params: { id: string; input: AddressInput }) =>
      updateCustomerAddress(params.id, params.input),
    onSuccess: invalidate,
  });
  const remove = useMutation({
    mutationFn: (id: string) => deleteCustomerAddress(id),
    onSuccess: invalidate,
  });
  const setDefault = useMutation({
    mutationFn: (id: string) => setDefaultCustomerAddress(id),
    onSuccess: invalidate,
  });

  return { create, update, remove, setDefault };
}
