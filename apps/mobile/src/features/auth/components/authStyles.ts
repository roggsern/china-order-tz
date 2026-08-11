import { StyleSheet } from 'react-native';

export const authStyles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    padding: 24,
    paddingBottom: 48,
  },
  heading: {
    fontSize: 24,
    fontWeight: '700',
    marginBottom: 8,
  },
  subheading: {
    fontSize: 14,
    color: '#555',
    marginBottom: 24,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    marginBottom: 6,
    color: '#222',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    marginBottom: 4,
    backgroundColor: '#fafafa',
  },
  inputError: {
    borderColor: '#b00020',
  },
  fieldError: {
    color: '#b00020',
    fontSize: 12,
    marginBottom: 12,
  },
  fieldSpacer: {
    marginBottom: 12,
  },
  banner: {
    backgroundColor: '#fdecea',
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
  },
  bannerText: {
    color: '#b00020',
    fontSize: 14,
  },
  button: {
    backgroundColor: '#0a7ea4',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  linkRow: {
    marginTop: 20,
    alignItems: 'center',
  },
  linkText: {
    color: '#0a7ea4',
    fontSize: 15,
  },
});
